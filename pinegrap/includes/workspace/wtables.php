<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - workflow tables. A person keeps tables of typed columns (text,
 * number, money, date, person, status, checkbox, record link, formula) whose
 * rows feed their flows. A table is its owner's alone, like a note: an
 * administrator reaches it only through a share.
 *
 * The first part needs no database: the column types, the options each
 * type takes, the check of one cell against its column and of a row's
 * cells, the limits, and how a row's cells are written as JSON. Every write
 * of a table goes through these checks, from the panel and the external API
 * alike, so that both refuse the same values with the same codes.
 *
 * The second part reads and writes the four tables of 2026.11.0 (11.0.110):
 * who reaches a table (ws_wtable_access()), and creating, renaming and
 * deleting tables and their columns. A table whose owner is gone (deleted,
 * taken out of the team by ws_profiles.excluded, or without the workspace
 * right) is reached by nobody, its owner and its shares alike.
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
        'description' => 2000,
        'text' => 10000,
        'statuses' => 50,
        'status_label' => 60,
        'formula' => 1000,
        'link_kinds' => 20,
        'list_items' => 100,
        'list_item' => 1000,
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
        'person' => lang('Person (team member)'),
        'status' => lang('Status'),
        'checkbox' => lang('Checkbox'),
        'link' => lang('Record link'),
        'list' => lang('List'),
        'row' => lang('Row link'),
        'formula' => lang('Formula'),
        'lookup' => lang('Lookup'),
    );
}

/**
 * What the items of a list column are: texts or numbers.
 *
 * @return array kind => label
 */
function ws_wtable_list_kinds()
{
    return array(
        'text' => lang('Texts'),
        'number' => lang('Numbers'),
    );
}

/**
 * The kind of the items of a list column, text when it names none.
 *
 * @param array $column type and options
 * @return string text | number
 */
function ws_wtable_list_of($column)
{
    $of = isset($column['options']['of']) ? (string) $column['options']['of'] : '';

    return isset(ws_wtable_list_kinds()[$of]) ? $of : 'text';
}

/**
 * Whether a column type keeps cells; the value of a formula and of a
 * lookup (wtable_lookup.php) is worked out when the row is read instead.
 *
 * @param string $type
 * @return bool
 */
function ws_wtable_type_stores($type)
{
    return isset(ws_wtable_column_types()[$type]) && !in_array($type, array('formula', 'lookup'), true);
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
 * record kinds a cell may point at (empty for any). list: of, what its
 * items are (text, the default, or number). row: table, the id of the
 * workflow table its cells link to (wtable_links.php). formula: expression, without
 * the leading =. lookup: link, a row link column of the table, and column, a
 * column of the linked table (wtable_lookup.php). text, person and checkbox
 * take none.
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

        case 'list':
            $of = array_key_exists('of', $options) ? $options['of'] : 'text';

            if (!is_string($of) || !isset(ws_wtable_list_kinds()[$of])) {
                return $fail('list_of_invalid');
            }

            $value['of'] = $of;
            break;

        case 'row':
            $linked = $options['table'] ?? null;

            if (!ws_wtable_is_whole($linked) || ((int) $linked <= 0)) {
                return $fail('row_table_invalid');
            }

            $value['table'] = (int) $linked;
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

        case 'lookup':
            foreach (array('link' => 'lookup_link_invalid', 'column' => 'lookup_column_invalid') as $key => $error) {
                $id = $options[$key] ?? null;

                if (!ws_wtable_is_whole($id) || ((int) $id <= 0)) {
                    return $fail($error);
                }

                $value[$key] = (int) $id;
            }

            break;
    }

    // A text or number column may be the table's key (wtable_keys.php).
    if (!empty($options['key'])) {
        if (!in_array($type, array('text', 'number'), true)) {
            return $fail('key_type');
        }

        $value['key'] = true;
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
 * is a kind and an id, the kind among the column's kinds when it names any;
 * a list is a list of texts or numbers (ws_wtable_list_check()); a row
 * link is a row id (whether that row is there is the store's check,
 * ws_wtable_link_cells_refusal()).
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

    if (!ws_wtable_type_stores($type)) {
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

        case 'list':
            $list = ws_wtable_list_check($value, ws_wtable_list_of($column));

            return $list['ok'] ? $ok($list['value']) : $fail($list['error']);

        case 'row':
            if (!ws_wtable_is_whole($value) || ((int) $value <= 0)) {
                return $fail('not_row');
            }

            return $ok((int) $value);
    }

    return $fail('type_unknown');
}

/**
 * The items a list cell is given: a list, its JSON text, or a text with one
 * item per line. Anything else is null.
 *
 * @param mixed $value
 * @return array|null
 */
function ws_wtable_list_items($value)
{
    if (is_array($value)) {
        return ($value === array_values($value)) ? $value : null;
    }

    if (is_int($value) || is_float($value)) {
        return array($value);
    }

    if (!is_string($value)) {
        return null;
    }

    $text = trim($value);

    if (($text !== '') && ($text[0] === '[')) {
        $decoded = json_decode($text, true);

        if (is_array($decoded) && ($decoded === array_values($decoded))) {
            return $decoded;
        }
    }

    return preg_split('/\r\n|\r|\n/', $value);
}

/**
 * A list cell checked, and the list to keep: items trimmed, empty items
 * dropped, at most list_items of them. A text item is at most list_item
 * characters; a number item is kept to six places, a whole one as an int.
 * No item left keeps null, an empty cell.
 *
 * @param mixed  $value
 * @param string $of    text | number
 * @return array ok, value, error (not_list, list_too_long, item_too_long, not_number, out_of_range)
 */
function ws_wtable_list_check($value, $of)
{
    $limits = ws_wtable_limits();
    $items = ws_wtable_list_items($value);

    if ($items === null) {
        return array('ok' => false, 'value' => null, 'error' => 'not_list');
    }

    $kept = array();

    foreach ($items as $item) {
        if (($item === null) || ($item === '')) {
            continue;
        }

        if (!is_scalar($item) || is_bool($item)) {
            return array('ok' => false, 'value' => null, 'error' => 'not_list');
        }

        if ($of === 'number') {
            $number = ws_wtable_list_number($item);

            if (!$number['ok']) {
                return array('ok' => false, 'value' => null, 'error' => $number['error']);
            }

            $kept[] = $number['value'];
        } else {
            $item = trim((string) $item);

            if ($item === '') {
                continue;
            }

            if (ws_wtable_length($item) > $limits['list_item']) {
                return array('ok' => false, 'value' => null, 'error' => 'item_too_long');
            }

            $kept[] = $item;
        }

        if (count($kept) > $limits['list_items']) {
            return array('ok' => false, 'value' => null, 'error' => 'list_too_long');
        }
    }

    return array('ok' => true, 'value' => empty($kept) ? null : $kept, 'error' => '');
}

/**
 * One number of a list: a figure in machine form, kept to six places, a
 * whole one as an int.
 *
 * @param mixed $item
 * @return array ok, value, error
 */
function ws_wtable_list_number($item)
{
    if (is_string($item)) {
        $item = trim($item);

        if (!preg_match('/^-?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][-+]?[0-9]+)?$/', $item)) {
            return array('ok' => false, 'value' => null, 'error' => 'not_number');
        }
    }

    $number = (float) $item;

    if (!is_finite($number) || (abs($number) >= 1e15)) {
        return array('ok' => false, 'value' => null, 'error' => 'out_of_range');
    }

    $number = round($number, 6);

    return array('ok' => true, 'value' => (floor($number) === $number) ? (int) $number : $number, 'error' => '');
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

/* ---------------------------------------------------------------------------
   Access (pure)
   --------------------------------------------------------------------------- */

/**
 * How far an access reaches: '' nothing, view, edit, owner.
 *
 * @param string $access
 * @return int 0-3
 */
function ws_wtable_access_rank($access)
{
    $ranks = array('view' => 1, 'edit' => 2, 'owner' => 3);

    return $ranks[(string) $access] ?? 0;
}

/**
 * What one share gives its holder: a person view or edit as stored, a
 * channel a view whatever is stored.
 *
 * @param string $target_type user | channel
 * @param string $level       view | edit
 * @return string edit | view | ''
 */
function ws_wtable_share_gives($target_type, $level)
{
    if (!in_array((string) $level, array('view', 'edit'), true)) {
        return '';
    }

    if ($target_type === 'channel') {
        return 'view';
    }

    return ($target_type === 'user') ? (string) $level : '';
}

/**
 * What a person may do with a table, from what is known about them and it:
 *   member  bool   they are in the team (ws_rights())
 *   owner   bool   the table is theirs
 *   present bool   its owner is in the team still: not deleted, not
 *                  excluded, holding the workspace right
 *   person  string the level the table is shared with them at, '' for none
 *   channel bool   the table is shared in a channel they read
 * Being an administrator gives nothing: a table is its owner's alone and
 * reaches others only through its shares.
 *
 * @param array $facts
 * @return string owner | edit | view | ''
 */
function ws_wtable_access_level($facts)
{
    if (empty($facts['member']) || empty($facts['present'])) {
        return '';
    }

    if (!empty($facts['owner'])) {
        return 'owner';
    }

    $best = ws_wtable_share_gives('user', (string) ($facts['person'] ?? ''));

    if (($best === '') && !empty($facts['channel'])) {
        $best = 'view';
    }

    return $best;
}

/**
 * May an access do a thing with a table? read: see it; write: change its
 * name, description, columns and rows; manage: delete it and its shares.
 *
 * @param string $access ws_wtable_access()
 * @param string $what   read | write | manage
 * @return bool
 */
function ws_wtable_may($access, $what)
{
    $need = array('read' => 'view', 'write' => 'edit', 'manage' => 'owner');

    return isset($need[$what]) && (ws_wtable_access_rank($access) >= ws_wtable_access_rank($need[$what]));
}

/* ---------------------------------------------------------------------------
   Checks of what is written (pure)
   --------------------------------------------------------------------------- */

/**
 * A refusal: the code, the field it is about and the HTTP status the API
 * answers with.
 *
 * @param string $error
 * @param string $field
 * @param int    $status
 * @return array
 */
function ws_wtable_refusal($error, $field = '', $status = 422)
{
    return array('ok' => false, 'error' => (string) $error, 'field' => (string) $field, 'status' => (int) $status);
}

/**
 * The name and description of a table, checked. On a change a key left out
 * keeps what the table has; a new table needs a name.
 *
 * @param mixed $input name, description
 * @param bool  $new
 * @return array ok, sets (name, description), error, field, status
 */
function ws_wtable_table_input_check($input, $new)
{
    $input = is_array($input) ? $input : array();
    $sets = array();

    if ($new || array_key_exists('name', $input)) {
        $name = ws_wtable_name_check($input['name'] ?? '');

        if (!$name['ok']) {
            return ws_wtable_refusal($name['error'], 'name');
        }

        $sets['name'] = $name['value'];
    }

    if (array_key_exists('description', $input)) {
        $description = $input['description'];

        if (($description !== null) && !is_scalar($description)) {
            return ws_wtable_refusal('description_invalid', 'description');
        }

        $description = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]+/u', ' ', str_replace(array("\r\n", "\r"), "\n", (string) $description));

        if (!is_string($description)) {
            return ws_wtable_refusal('description_invalid', 'description');
        }

        $description = trim($description);

        if (ws_wtable_length($description) > ws_wtable_limits()['description']) {
            return ws_wtable_refusal('description_too_long', 'description');
        }

        $sets['description'] = $description;
    }

    if (empty($sets)) {
        return ws_wtable_refusal('nothing_to_change');
    }

    return array('ok' => true, 'sets' => $sets, 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * A column's name, type and options, checked. On a change a key left out
 * keeps what the column has, and its type stays: cells already written
 * belong to it.
 *
 * @param mixed      $input name, type, options
 * @param array|null $old   the column, null for a new one
 * @return array ok, sets (name, type, options), error, field, status
 */
function ws_wtable_column_input_check($input, $old = null)
{
    $input = is_array($input) ? $input : array();
    $sets = array();

    if (($old === null) || array_key_exists('name', $input)) {
        $name = ws_wtable_name_check($input['name'] ?? '');

        if (!$name['ok']) {
            return ws_wtable_refusal($name['error'], 'name');
        }

        $sets['name'] = $name['value'];
    }

    if ($old === null) {
        $type = isset($input['type']) && is_string($input['type']) ? $input['type'] : '';

        if (!isset(ws_wtable_column_types()[$type])) {
            return ws_wtable_refusal('type_unknown', 'type');
        }

        $sets['type'] = $type;
    } else {
        $type = (string) $old['type'];

        if (array_key_exists('type', $input) && ($input['type'] !== $type)) {
            return ws_wtable_refusal('type_fixed', 'type');
        }

        // What a list holds is part of its type: its written items belong to it.
        if (($type === 'list') && array_key_exists('options', $input) && is_array($input['options'])
            && array_key_exists('of', $input['options']) && ($input['options']['of'] !== ws_wtable_list_of($old))) {
            return ws_wtable_refusal('type_fixed', 'options');
        }

        // So is the table a row link points at: its cells are that table's rows.
        if (($type === 'row') && array_key_exists('options', $input) && is_array($input['options'])
            && array_key_exists('table', $input['options']) && ((int) $input['options']['table'] !== ws_wtable_link_table($old))) {
            return ws_wtable_refusal('type_fixed', 'options');
        }
    }

    if (($old === null) || array_key_exists('options', $input)) {
        $given = (isset($input['options']) && is_array($input['options'])) ? $input['options'] : array();

        if (($old !== null) && ($type === 'list') && !array_key_exists('of', $given)) {
            $given['of'] = ws_wtable_list_of($old);
        }

        if (($old !== null) && ($type === 'row') && !array_key_exists('table', $given)) {
            $given['table'] = ws_wtable_link_table($old);
        }

        // Options sent without the key keep it: only key false takes it away.
        if (($old !== null) && !array_key_exists('key', $given) && !empty($old['options']['key'])) {
            $given['key'] = true;
        }

        $options = ws_wtable_column_options_check($type, $given);

        if (!$options['ok']) {
            return ws_wtable_refusal($options['error'], 'options');
        }

        $sets['options'] = $options['value'];
    }

    if (empty($sets)) {
        return ws_wtable_refusal('nothing_to_change');
    }

    return array('ok' => true, 'sets' => $sets, 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * A column's options as kept in its TEXT column: a JSON object, '{}' when
 * the type takes none.
 *
 * @param array $options
 * @return string
 */
function ws_wtable_options_encode($options)
{
    $json = json_encode((object) $options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return ($json === false) ? '{}' : $json;
}

/**
 * A column row as read from the table, its options decoded.
 *
 * @param array $row
 * @return array id, table_id, name, type, options, position, created_at
 */
function ws_wtable_column_from_row($row)
{
    $options = is_string($row['options'] ?? null) ? json_decode($row['options'], true) : null;

    return array(
        'id' => (int) $row['id'],
        'table_id' => (int) $row['table_id'],
        'name' => (string) $row['name'],
        'type' => (string) $row['type'],
        'options' => is_array($options) ? $options : array(),
        'position' => (int) $row['position'],
        'created_at' => (int) $row['created_at'],
    );
}

/* ---------------------------------------------------------------------------
   Reading
   --------------------------------------------------------------------------- */

/**
 * Are the four tables there (2026.11.0, 11.0.110)?
 *
 * @return bool
 */
function ws_wtables_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has')
            && pg_schema_has('ws_wtables')
            && pg_schema_has('ws_wtable_columns')
            && pg_schema_has('ws_wtable_rows')
            && pg_schema_has('ws_wtable_shares');
    }

    return $ready;
}

/**
 * Are the stable row numbers there (step 11.0.183): the table's counter,
 * the row's number and the binned row's number? Before the upgrade runs,
 * rows have no number and the screen shows their place instead.
 *
 * @return bool
 */
function ws_wtable_numbers_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_wtables_ready()
            && pg_schema_has('ws_wtables', 'next_number')
            && pg_schema_has('ws_wtable_rows', 'number')
            && (!pg_schema_has('ws_wtable_row_bin') || pg_schema_has('ws_wtable_row_bin', 'number'));
    }

    return $ready;
}

/**
 * One table, never one in the bin (ws_wtable_binned() reads those).
 *
 * @param int $table_id
 * @return array|null
 */
function ws_wtable($table_id)
{
    if (!ws_wtables_ready() || ((int) $table_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_wtables WHERE id = '" . (int) $table_id . "'" . ws_wtable_live_sql());

    return is_array($row) ? $row : null;
}

/**
 * Is the owner of a table in the team still? Asked once a request per
 * owner: a list asks it for every table.
 *
 * @param int $user_id
 * @return bool
 */
function ws_wtable_owner_present($user_id)
{
    static $seen = array();

    $user_id = (int) $user_id;

    if (!isset($seen[$user_id])) {
        $seen[$user_id] = ($user_id > 0) && !empty(ws_rights_for_id($user_id)['member']);
    }

    return $seen[$user_id];
}

/**
 * What the shares of a table give the person: the level of the one made to
 * them, and whether it is shared in a channel they read.
 *
 * @param array $viewer
 * @param int   $table_id
 * @return array person (view | edit | ''), channel (bool)
 */
function ws_wtable_share_reach($viewer, $table_id)
{
    $reach = array('person' => '', 'channel' => false);

    $shares = (array) db_items("SELECT target_type, target_id, level FROM ws_wtable_shares WHERE table_id = '" . (int) $table_id . "'");

    foreach ($shares as $share) {
        if (((string) $share['target_type'] === 'user') && ((int) $share['target_id'] === (int) $viewer['id'])) {
            $reach['person'] = (string) $share['level'];
        } elseif (((string) $share['target_type'] === 'channel') && !$reach['channel']) {
            $reach['channel'] = ws_flow_channel_readable($viewer, (int) $share['target_id']);
        }
    }

    return $reach;
}

/**
 * What the person may do with a table (ws_wtable_access_level() decides
 * from what is gathered here):
 *   owner  the table is theirs, while they are in the team
 *   edit   a person it is shared with at edit
 *   view   a person it is shared with at view; a channel share in a
 *          channel they read
 *   ''     nothing: somebody else's table that reaches them in none of these
 *          ways, however high their role, and every table whose owner left
 *          the team
 *
 * @param array      $viewer
 * @param array|null $table
 * @return string owner | edit | view | ''
 */
function ws_wtable_access($viewer, $table)
{
    if (!is_array($table) || empty($viewer['member'])) {
        return '';
    }

    $owner = ((int) $table['user_id'] === (int) $viewer['id']);
    $present = ws_wtable_owner_present((int) $table['user_id']);

    $reach = (!$owner && $present) ? ws_wtable_share_reach($viewer, (int) $table['id']) : array('person' => '', 'channel' => false);

    return ws_wtable_access_level(array(
        'member'  => true,
        'owner'   => $owner,
        'present' => $present,
        'person'  => $reach['person'],
        'channel' => $reach['channel'],
    ));
}

/**
 * A table the person may reach at least as far as asked.
 *
 * @param array  $viewer
 * @param int    $table_id
 * @param string $need view | edit | owner
 * @return array|null the row, with access
 */
function ws_wtable_for($viewer, $table_id, $need = 'view')
{
    $table = ws_wtable($table_id);
    $access = ws_wtable_access($viewer, $table);

    if (($access === '') || (ws_wtable_access_rank($access) < ws_wtable_access_rank($need))) {
        return null;
    }

    $table['access'] = $access;

    return $table;
}

/**
 * The columns of a table in order, by id.
 *
 * @param int $table_id
 * @return array column id => column (ws_wtable_column_from_row())
 */
function ws_wtable_columns($table_id)
{
    if (!ws_wtables_ready() || ((int) $table_id <= 0)) {
        return array();
    }

    $columns = array();

    foreach ((array) db_items("SELECT * FROM ws_wtable_columns WHERE table_id = '" . (int) $table_id . "' ORDER BY position, id") as $row) {
        $columns[(string) $row['id']] = ws_wtable_column_from_row($row);
    }

    return $columns;
}

/**
 * The tables the person owns, the latest changed first; binned ones are
 * left out.
 *
 * @param array $viewer
 * @return array[] rows, each with access owner
 */
function ws_wtables_own($viewer)
{
    if (!ws_wtables_ready() || empty($viewer['member'])) {
        return array();
    }

    $rows = (array) db_items("SELECT id, user_id, name, description, row_count, revision, created_at, updated_at
        FROM ws_wtables WHERE user_id = '" . (int) $viewer['id'] . "'" . ws_wtable_live_sql() . " ORDER BY updated_at DESC, id DESC");

    foreach ($rows as $i => $row) {
        $rows[$i]['access'] = 'owner';
    }

    return $rows;
}

/* ---------------------------------------------------------------------------
   Tables: create, change, delete
   --------------------------------------------------------------------------- */

/**
 * May the person make a table, and is what they give right? The panel and
 * the API's dry run ask this alone.
 *
 * @param array $viewer
 * @param mixed $input name, description
 * @return array ok, sets, error, field, status
 */
function ws_wtable_create_check($viewer, $input)
{
    if (!ws_wtables_ready()) {
        return ws_wtable_refusal('not_ready', '', 503);
    }

    if (empty($viewer['member']) || ((int) $viewer['id'] <= 0)) {
        return ws_wtable_refusal('forbidden', '', 403);
    }

    $check = ws_wtable_table_input_check($input, true);

    if (!$check['ok']) {
        return $check;
    }

    // A binned table does not count in the limit.
    $count = (int) db_value("SELECT COUNT(*) FROM ws_wtables WHERE user_id = '" . (int) $viewer['id'] . "'" . ws_wtable_live_sql());

    if (!ws_wtable_limit_allows('tables', $count)) {
        return ws_wtable_refusal('too_many_tables', '', 409);
    }

    return $check;
}

/**
 * Make a table, owned by the person, with no columns yet.
 *
 * @param array $viewer
 * @param mixed $input name, description
 * @return array ok, error, field, status, table_id
 */
function ws_wtable_create($viewer, $input)
{
    $check = ws_wtable_create_check($viewer, $input);

    if (!$check['ok']) {
        return $check + array('table_id' => 0);
    }

    $now = time();

    db("INSERT INTO ws_wtables (user_id, name, description, row_count, revision, created_at, updated_at)
        VALUES ('" . (int) $viewer['id'] . "', '" . e($check['sets']['name']) . "', '" . e($check['sets']['description'] ?? '') . "',
            0, 1, '" . $now . "', '" . $now . "')");

    $table_id = (int) mysqli_insert_id(db::$con);

    if ($table_id <= 0) {
        return ws_wtable_refusal('not_saved', '', 500) + array('table_id' => 0);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 201, 'table_id' => $table_id);
}

/**
 * May the person rename a table or change its description?
 *
 * @param array      $viewer
 * @param array|null $table ws_wtable()
 * @param mixed      $input name, description
 * @return array ok, sets, error, field, status
 */
function ws_wtable_update_check($viewer, $table, $input)
{
    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write')) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    return ws_wtable_table_input_check($input, false);
}

/**
 * Rename a table or change its description.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param mixed      $input
 * @return array ok, error, field, status
 */
function ws_wtable_update($viewer, $table, $input)
{
    $check = ws_wtable_update_check($viewer, $table, $input);

    if (!$check['ok']) {
        return $check;
    }

    $sets = array();

    foreach ($check['sets'] as $key => $value) {
        $sets[] = $key . " = '" . e($value) . "'";
    }

    db("UPDATE ws_wtables SET " . implode(', ', $sets) . ", updated_at = '" . time() . "' WHERE id = '" . (int) $table['id'] . "'");

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * May the person delete a table? Its owner alone.
 *
 * @param array      $viewer
 * @param array|null $table
 * @return array ok, error, field, status
 */
function ws_wtable_delete_check($viewer, $table)
{
    $access = ws_wtable_access($viewer, $table);

    if ($access === '') {
        return ws_wtable_refusal('not_found', '', 404);
    }

    if (!ws_wtable_may($access, 'manage')) {
        return ws_wtable_refusal('forbidden', '', 403);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * Delete a table: it goes to the bin whole (ws_wtable_bin_put()), where its
 * owner may restore it for the Recycle Bin's days; before the bin's step has
 * run, it is deleted for good.
 *
 * @param array      $viewer
 * @param array|null $table
 * @return array ok, error, field, status
 */
function ws_wtable_delete($viewer, $table)
{
    $check = ws_wtable_delete_check($viewer, $table);

    if (!$check['ok']) {
        return $check;
    }

    if (!ws_wtable_bin_ready()) {
        ws_wtable_delete_final((int) $table['id']);

        return $check;
    }

    if (!ws_wtable_bin_put($table, (int) $viewer['id'])) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    return $check;
}

/**
 * Delete a table for good with its columns, rows, binned rows, the rows'
 * tags in ws_refs and its shares: the purge of the bin, and the rollback of
 * a table made halfway (an import, a starter). The table goes first, so that
 * a request stopped halfway leaves nothing anybody reaches.
 *
 * @param int $table_id
 */
function ws_wtable_delete_final($table_id)
{
    $id = (int) $table_id;

    if (!ws_wtables_ready() || ($id <= 0)) {
        return;
    }

    db("DELETE FROM ws_wtables WHERE id = '" . $id . "'");

    // The rows' tags are found through the rows, so they go first.
    ws_ref_source_write('wtable_row', array_fill_keys(array_map('intval', (array) db_values("SELECT id FROM ws_wtable_rows WHERE table_id = '" . $id . "'")), array()));

    foreach (array('ws_wtable_columns', 'ws_wtable_rows', 'ws_wtable_shares') as $child) {
        db("DELETE FROM " . $child . " WHERE table_id = '" . $id . "'");
    }

    if (ws_wtable_bin_ready()) {
        db("DELETE FROM ws_wtable_row_bin WHERE table_id = '" . $id . "'");
    }
}

/* ---------------------------------------------------------------------------
   Columns: create, change, delete
   --------------------------------------------------------------------------- */

/**
 * Mark a table's columns changed: its revision moves on, so that a row
 * written against the columns it had is refused, and it counts as changed.
 *
 * @param int $table_id
 */
function ws_wtable_columns_changed($table_id)
{
    db("UPDATE ws_wtables SET revision = revision + 1, updated_at = '" . time() . "' WHERE id = '" . (int) $table_id . "'");
}

/**
 * May the person add a column to a table, and is what they give right?
 *
 * @param array      $viewer
 * @param array|null $table
 * @param mixed      $input name, type, options
 * @return array ok, sets, error, field, status
 */
function ws_wtable_column_create_check($viewer, $table, $input)
{
    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write')) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    $check = ws_wtable_column_input_check($input);

    if (!$check['ok']) {
        return $check;
    }

    $count = (int) db_value("SELECT COUNT(*) FROM ws_wtable_columns WHERE table_id = '" . (int) $table['id'] . "'");

    if (!ws_wtable_limit_allows('columns', $count)) {
        return ws_wtable_refusal('too_many_columns', '', 409);
    }

    $refused = ws_wtable_column_key_check($table, null, $check['sets']);

    if ($refused === null) {
        $refused = ws_wtable_link_column_refusal($viewer, $check['sets']);
    }

    if ($refused === null) {
        $refused = ws_wtable_lookup_column_refusal($viewer, $table, $check['sets']);
    }

    return ($refused !== null) ? $refused : $check;
}

/**
 * Why a column cannot become the table's key, null when it can or does
 * not ask to: key_taken (409) when another column is the key, then
 * key_not_unique with the rows that are empty or repeat a value. A new
 * column is empty on every row, so it becomes the key of an empty table
 * only.
 *
 * @param array      $table
 * @param array|null $column the column changed, null for a new one
 * @param array      $sets   ws_wtable_column_input_check() sets
 * @return array|null
 */
function ws_wtable_column_key_check($table, $column, $sets)
{
    if (empty($sets['options']['key']) || ($column && !empty($column['options']['key']))) {
        return null;
    }

    foreach (ws_wtable_columns((int) $table['id']) as $other) {
        if (ws_wtable_is_key($other) && (!$column || ((int) $other['id'] !== (int) $column['id']))) {
            return ws_wtable_refusal('key_taken', 'options', 409);
        }
    }

    $probe = array('id' => $column ? (int) $column['id'] : 0, 'type' => $column ? $column['type'] : $sets['type'], 'options' => $sets['options']);

    return ws_wtable_key_refusal(ws_wtable_key_faults($probe, ws_wtable_key_rows((int) $table['id'])));
}

/**
 * Add a column at the end of a table.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param mixed      $input
 * @return array ok, error, field, status, column_id
 */
function ws_wtable_column_create($viewer, $table, $input)
{
    $check = ws_wtable_column_create_check($viewer, $table, $input);

    if (!$check['ok']) {
        return $check + array('column_id' => 0);
    }

    $table_id = (int) $table['id'];
    $position = (int) db_value("SELECT COALESCE(MAX(position), 0) FROM ws_wtable_columns WHERE table_id = '" . $table_id . "'") + 1;

    db("INSERT INTO ws_wtable_columns (table_id, name, type, options, position, created_at)
        VALUES ('" . $table_id . "', '" . e($check['sets']['name']) . "', '" . e($check['sets']['type']) . "',
            '" . e(ws_wtable_options_encode($check['sets']['options'])) . "', '" . $position . "', '" . time() . "')");

    $column_id = (int) mysqli_insert_id(db::$con);

    if ($column_id <= 0) {
        return ws_wtable_refusal('not_saved', '', 500) + array('column_id' => 0);
    }

    ws_wtable_columns_changed($table_id);

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 201, 'column_id' => $column_id);
}

/**
 * One column of a table, or null when the table has no such column.
 *
 * @param int $table_id
 * @param int $column_id
 * @return array|null ws_wtable_column_from_row()
 */
function ws_wtable_column($table_id, $column_id)
{
    if (!ws_wtables_ready() || ((int) $column_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_wtable_columns WHERE id = '" . (int) $column_id . "' AND table_id = '" . (int) $table_id . "'");

    return is_array($row) ? ws_wtable_column_from_row($row) : null;
}

/**
 * May the person rename a column or change its options?
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $column ws_wtable_column()
 * @param mixed      $input  name, options (type stays)
 * @return array ok, sets, error, field, status
 */
function ws_wtable_column_update_check($viewer, $table, $column, $input)
{
    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write') || !is_array($column)) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    $check = ws_wtable_column_input_check($input, $column);

    if (!$check['ok']) {
        return $check;
    }

    $refused = ws_wtable_column_key_check($table, $column, $check['sets']);

    if ($refused === null) {
        $refused = ws_wtable_lookup_column_refusal($viewer, $table, array('type' => (string) $column['type']) + $check['sets']);
    }

    return ($refused !== null) ? $refused : $check;
}

/**
 * Rename a column or change its options.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $column
 * @param mixed      $input
 * @return array ok, error, field, status
 */
function ws_wtable_column_update($viewer, $table, $column, $input)
{
    $check = ws_wtable_column_update_check($viewer, $table, $column, $input);

    if (!$check['ok']) {
        return $check;
    }

    $sets = array();

    if (isset($check['sets']['name'])) {
        $sets[] = "name = '" . e($check['sets']['name']) . "'";
    }

    if (isset($check['sets']['options'])) {
        $sets[] = "options = '" . e(ws_wtable_options_encode($check['sets']['options'])) . "'";
    }

    db("UPDATE ws_wtable_columns SET " . implode(', ', $sets) . " WHERE id = '" . (int) $column['id'] . "'");

    ws_wtable_columns_changed((int) $table['id']);

    return $check;
}

/**
 * May the person delete a column? The panel and the API's dry run ask this
 * alone.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $column
 * @return array ok, error, field, status
 */
function ws_wtable_column_delete_check($viewer, $table, $column)
{
    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write') || !is_array($column)) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * Delete a column. Its cells stay in the rows' JSON until each row is next
 * written (ws_wtable_cells_merge() drops a column the table no longer has),
 * so that a column of a 5000-row table goes without rewriting every row;
 * reads skip them, and a column id is never given again.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $column
 * @return array ok, error, field, status
 */
function ws_wtable_column_delete($viewer, $table, $column)
{
    $check = ws_wtable_column_delete_check($viewer, $table, $column);

    if (!$check['ok']) {
        return $check;
    }

    db("DELETE FROM ws_wtable_columns WHERE id = '" . (int) $column['id'] . "' AND table_id = '" . (int) $table['id'] . "'");

    ws_wtable_columns_changed((int) $table['id']);

    return $check;
}
