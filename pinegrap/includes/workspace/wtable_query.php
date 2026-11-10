<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a query over the rows of a workflow table, as the table
 * screen's query bar and the rows API ask it: the conditions of
 * wtable_where.php joined by all or any, and a sort by one column. Read
 * from the screen's address or the API's query, checked against the
 * table's columns, then worked out over the rows a read gives and paged by
 * an offset into the rows it keeps. Nothing here reads the database.
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
 * A query as given: its conditions (ws_wtable_where_list(), null when the
 * text is no list), how they join, the sort column (0 for the table's
 * order) and its direction. Unknown words fall back to all and asc. Pure.
 *
 * @param mixed  $where list or its JSON text
 * @param string $join
 * @param mixed  $sort
 * @param string $dir
 * @return array where, join, sort, dir
 */
function ws_wtable_query($where, $join = 'all', $sort = 0, $dir = 'asc')
{
    return array(
        'where' => ws_wtable_where_list($where),
        'join'  => in_array((string) $join, ws_wtable_where_joins(), true) ? (string) $join : 'all',
        'sort'  => (is_scalar($sort) && ctype_digit((string) $sort)) ? (int) $sort : 0,
        'dir'   => in_array((string) $dir, ws_wtable_sort_directions(), true) ? (string) $dir : 'asc',
    );
}

/**
 * Does a query change which rows show or their order? Pure.
 *
 * @param array $query ws_wtable_query()
 * @return bool
 */
function ws_wtable_query_active($query)
{
    return !empty($query['where']) || ((int) ($query['sort'] ?? 0) > 0) || ((string) ($query['dir'] ?? 'asc') === 'desc');
}

/**
 * A fault of ws_wtable_where_value_fault() in words, naming the column.
 * Pure but for lang().
 *
 * @param array  $column
 * @param string $fault
 * @param string $value as typed
 * @return string
 */
function ws_wtable_where_fault_text($column, $fault, $value)
{
    $name = (string) ($column['name'] ?? '');
    $shown = mb_substr(trim((string) $value), 0, 60);

    switch ((string) $fault) {
        case 'operator':
            return lang(array('string' => 'The column “{var:1}” cannot be compared that way.', 'vars' => $name));

        case 'empty_value':
            return lang('Give the value it compares with.');

        case 'not_number':
            return lang(array('string' => 'The column “{var:1}” holds numbers, written as 12.5: “{var:2}” is not one.', 'vars' => array($name, $shown)));

        case 'not_minor_units':
            return lang(array('string' => 'The column “{var:1}” holds money in minor units, 1250 for 12.50: “{var:2}” is not a whole number.', 'vars' => array($name, $shown)));

        case 'not_date':
            return lang(array('string' => 'The column “{var:1}” holds dates, written as 2026-11-09 or 2026-11-09 14:30: “{var:2}” is not one.', 'vars' => array($name, $shown)));

        case 'not_status':
            return lang(array('string' => 'The column “{var:1}” has no status with the key “{var:2}”.', 'vars' => array($name, $shown)));

        case 'not_checkbox':
            return lang(array('string' => 'The column “{var:1}” is a checkbox: compare it with 1 (ticked) or 0 (not ticked).', 'vars' => $name));

        case 'not_whole':
            return lang(array('string' => 'The column “{var:1}” is compared by a number, a person\'s or a record\'s: “{var:2}” is not one.', 'vars' => array($name, $shown)));
    }

    return '';
}

/**
 * Why a query does not fit its table, '' when it does: conditions that are
 * no list, too many of them, then per condition a missing column or
 * comparison, a column the table does not have, a comparison its type does
 * not take or a value that does not fit it, and last a sort column the
 * table does not have. Pure but for lang().
 *
 * @param array $columns column id => column (ws_wtable_columns())
 * @param array $query   ws_wtable_query()
 * @return string
 */
function ws_wtable_query_refusal($columns, $query)
{
    if (!is_array($query['where'] ?? null)) {
        return lang('The conditions are not a JSON list of conditions.');
    }

    if (count($query['where']) > WS_WTABLE_WHERE_MAX) {
        return lang(array('string' => 'At most {var:1} conditions.', 'vars' => WS_WTABLE_WHERE_MAX));
    }

    foreach ($query['where'] as $place => $condition) {
        $said = '';
        $column = $columns[(string) (int) $condition['column']] ?? null;

        if ((int) $condition['column'] <= 0) {
            $said = lang('Choose its column.');
        } elseif ((string) $condition['op'] === '') {
            $said = lang('Choose how it compares.');
        } elseif (!is_array($column)) {
            $said = lang('It names a column the table no longer has.');
        } else {
            $fault = ws_wtable_where_value_fault($column, (string) $condition['op'], (string) $condition['value']);

            if ($fault === 'operator') {
                $said = lang(array('string' => 'The column “{var:1}” cannot be compared with “{var:2}”.',
                    'vars' => array((string) $column['name'], (string) (ws_wtable_where_operators()[(string) $condition['op']] ?? ''))));
            } elseif ($fault !== '') {
                $said = ws_wtable_where_fault_text($column, $fault, (string) $condition['value']);
            }
        }

        if ($said !== '') {
            return lang(array('string' => 'Condition {var:1}: {var:2}', 'vars' => array($place + 1, $said)));
        }
    }

    $sort = (int) ($query['sort'] ?? 0);

    return (($sort > 0) && !isset($columns[(string) $sort])) ? lang('The rows are sorted by a column the table does not have.') : '';
}

/**
 * The rows a query keeps, in its order: those meeting its conditions,
 * sorted by its column (ws_wtable_rows_sorted()). Pure.
 *
 * @param array   $columns
 * @param array[] $rows  as read, in the table's order
 * @param array   $query ws_wtable_query(), checked
 * @return array[]
 */
function ws_wtable_query_rows($columns, $rows, $query)
{
    $kept = ws_wtable_rows_where($columns, $rows, (array) ($query['where'] ?? array()), (string) ($query['join'] ?? 'all'));

    return ws_wtable_rows_sorted($columns, $kept, (int) ($query['sort'] ?? 0), (string) ($query['dir'] ?? 'asc'));
}

/**
 * One page of the rows a query keeps: from an offset, at most $limit, and
 * the offset of the next page (null on the last). Pure.
 *
 * @param array[] $rows
 * @param int     $from
 * @param int     $limit
 * @return array rows, next
 */
function ws_wtable_query_page($rows, $from, $limit)
{
    $rows = array_values((array) $rows);
    $from = max(0, (int) $from);
    $limit = max(1, (int) $limit);

    return array(
        'rows' => array_slice($rows, $from, $limit),
        'next' => (count($rows) > $from + $limit) ? $from + $limit : null,
    );
}

/* ---------------------------------------------------------------------------
   The screen's query bar
   --------------------------------------------------------------------------- */

/**
 * The screen's query from its address: conditions as typed (q[n][c],
 * q[n][o], q[n][v]), a line without a column left out (the bar's empty
 * line), at most WS_WTABLE_WHERE_MAX of them; join, sort and dir. Pure.
 *
 * @param array $get
 * @return array typed (column, op, value as typed), join, sort, dir
 */
function ws_wtable_query_from_get($get)
{
    $typed = array();

    foreach ((is_array($get['q'] ?? null) ? $get['q'] : array()) as $line) {
        if (!is_array($line) || !is_string($line['c'] ?? null) || !ctype_digit($line['c']) || ((int) $line['c'] <= 0)) {
            continue;
        }

        $op = is_string($line['o'] ?? null) ? $line['o'] : '';
        $value = is_string($line['v'] ?? null) ? mb_substr($line['v'], 0, 200) : '';

        $typed[] = array(
            'column' => (int) $line['c'],
            'op'     => isset(ws_wtable_where_operators()[$op]) ? $op : '',
            'value'  => $value,
        );

        if (count($typed) === WS_WTABLE_WHERE_MAX) {
            break;
        }
    }

    $query = ws_wtable_query(array(), is_string($get['join'] ?? null) ? $get['join'] : 'all', is_string($get['sort'] ?? null) ? $get['sort'] : 0, is_string($get['dir'] ?? null) ? $get['dir'] : 'asc');
    unset($query['where']);

    return array('typed' => $typed) + $query;
}

/**
 * A value typed into the bar as a condition compares it: a figure and money
 * the site's way (money to minor units, rounded), a date as ISO or in the
 * site's order, a status by its label or key, a checkbox by yes or no, a
 * person by name or number, a record as kind:id or its number. What it
 * cannot read is kept as typed, so the check names it. Pure but for lang().
 *
 * @param array  $column
 * @param string $text
 * @param array  $people user id => brief (name)
 * @param string $date_format DATE_FORMAT: month_day or day_month
 * @return string
 */
function ws_wtable_query_screen_value($column, $text, $people = array(), $date_format = '')
{
    $text = trim((string) $text);
    $type = (string) ($column['type'] ?? '');

    if ($text === '') {
        return '';
    }

    switch ($type) {
        case 'money':
            $minor = ws_wtable_screen_cell_from_form($column, $text);

            return is_int($minor) ? (string) $minor : $text;

        case 'number':
        case 'formula':
            $number = ws_wtable_screen_figure($text);

            return ($number === null) ? $text : ws_wtable_screen_number_text(is_float($number) ? $number : (float) $number);

        case 'date':
            $text = str_replace('T', ' ', $text);

            if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})( \d{1,2}:\d{2})?$/', $text, $parts)) {
                list($day, $month) = ($date_format === 'month_day') ? array($parts[2], $parts[1]) : array($parts[1], $parts[2]);
                $time = isset($parts[4]) ? ' ' . str_pad(trim($parts[4]), 5, '0', STR_PAD_LEFT) : '';

                return sprintf('%04d-%02d-%02d', (int) $parts[3], (int) $month, (int) $day) . $time;
            }

            return $text;

        case 'status':
            foreach ((array) ($column['options']['choices'] ?? array()) as $choice) {
                if (ws_fold((string) ($choice['label'] ?? '')) === ws_fold($text)) {
                    return (string) $choice['key'];
                }
            }

            return $text;

        case 'checkbox':
            $folded = ws_fold($text);

            if (in_array($folded, array('1', 'true', ws_fold(lang('Yes')), ws_fold(lang('Ticked'))), true)) {
                return '1';
            }

            return in_array($folded, array('0', 'false', ws_fold(lang('No')), ws_fold(lang('Not ticked'))), true) ? '0' : $text;

        case 'person':
            foreach ((array) $people as $id => $person) {
                if (ws_fold((string) ($person['name'] ?? '')) === ws_fold($text)) {
                    return (string) (int) $id;
                }
            }

            return $text;

        case 'link':
            return preg_match('/^[a-z_]{1,32}\s*[:#]\s*([0-9]{1,18})$/i', $text, $parts) ? $parts[1] : $text;
    }

    return $text;
}

/**
 * The address parameters of the screen's query, for links that keep it
 * (pages, sorting, editing a row): only what differs from the defaults.
 * Pure.
 *
 * @param array $screen ws_wtable_query_from_get()
 * @return array
 */
function ws_wtable_query_params($screen)
{
    $params = array();

    foreach (array_values((array) ($screen['typed'] ?? array())) as $place => $line) {
        $params['q'][$place] = array('c' => (string) (int) $line['column'], 'o' => (string) $line['op'], 'v' => (string) $line['value']);
    }

    if (!empty($params['q']) && ((string) ($screen['join'] ?? 'all') === 'any')) {
        $params['join'] = 'any';
    }

    if ((int) ($screen['sort'] ?? 0) > 0) {
        $params['sort'] = (string) (int) $screen['sort'];
    }

    if ((string) ($screen['dir'] ?? 'asc') === 'desc') {
        $params['dir'] = 'desc';
    }

    return $params;
}

/**
 * The total of one statistic as the totals line shows it: money in the
 * column's decimals and currency, a number or formula as a figure, '' for
 * none. Pure but for the site's separators.
 *
 * @param array          $column
 * @param int|float|null $value
 * @return string
 */
function ws_wtable_query_total_text($column, $value)
{
    if ($value === null) {
        return '';
    }

    if ((string) ($column['type'] ?? '') === 'money') {
        return ws_wtable_screen_cell_text($column, (int) round($value));
    }

    return ws_wtable_screen_cell_text(array('type' => 'number'), is_int($value) ? $value : (float) $value);
}
