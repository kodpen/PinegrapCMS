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

/**
 * The query bar above a table's rows: a line per condition as typed and an
 * empty one to add, each a column, a comparison and a value (a list for a
 * status, a checkbox and a person, a date picker for a date), how the
 * conditions join, the sort, Apply and Clear. A GET form, so a filtered
 * view is a link that can be kept. The refusal of a query that does not
 * fit is shown under it.
 *
 * @param string $self_url  workspace_wtables.php
 * @param int    $table_id
 * @param array  $columns   column id => column
 * @param array  $screen    ws_wtable_query_from_get()
 * @param array  $team      ws_team_members()
 * @param string $refusal   ws_wtable_query_refusal(), '' when it fits
 * @param bool   $totals_all the totals are over every row kept
 * @return string HTML
 */
function ws_wtable_screen_query_bar($self_url, $table_id, $columns, $screen, $team, $refusal, $totals_all)
{
    $table_url = $self_url . '?id=' . (int) $table_id;
    $typed = array_values((array) $screen['typed']);
    $screen['typed'] = $typed;
    $lines = $typed;

    if (count($lines) < WS_WTABLE_WHERE_MAX) {
        $lines[] = array('column' => 0, 'op' => '', 'value' => '');
    }

    $operators = ws_wtable_where_operators();
    $out = '';

    foreach ($lines as $place => $line) {
        $column = $columns[(string) (int) $line['column']] ?? null;
        $type = is_array($column) ? (string) $column['type'] : '';
        $name = 'q[' . $place . ']';
        $choices = '<option value="">' . h(lang('Column…')) . '</option>';

        foreach ($columns as $column_id => $item) {
            $choices .= '<option value="' . (int) $column_id . '"' . (((int) $column_id === (int) $line['column']) ? ' selected' : '') . '>' . h($item['name']) . '</option>';
        }

        $ops = '<option value="">' . h(lang('Comparison…')) . '</option>';

        foreach ((($type !== '') ? ws_wtable_where_type_operators($type) : array_keys($operators)) as $op) {
            $ops .= '<option value="' . h($op) . '"' . (($op === (string) $line['op']) ? ' selected' : '') . '>' . h($operators[$op]) . '</option>';
        }

        $value = (string) $line['value'];
        $value_label = ' aria-label="' . h(lang('Value')) . '"';
        $list = null;

        if ($type === 'status') {
            $list = array();

            foreach ((array) ($column['options']['choices'] ?? array()) as $choice) {
                $list[(string) $choice['key']] = (string) $choice['label'];
            }

            $value = ws_wtable_query_screen_value($column, $value);
        } elseif ($type === 'checkbox') {
            $list = array('1' => lang('Ticked'), '0' => lang('Not ticked'));
            $value = ws_wtable_query_screen_value($column, $value);
        } elseif ($type === 'person') {
            $list = array();

            foreach ((array) $team as $person) {
                $list[(string) (int) $person['id']] = (string) $person['name'];
            }
        }

        if ($list !== null) {
            $field = '<option value=""></option>';

            foreach ($list as $key => $label) {
                $field .= '<option value="' . h($key) . '"' . (((string) $key === $value) ? ' selected' : '') . '>' . h($label) . '</option>';
            }

            $field = '<select class="form-control form-control-sm" name="' . $name . '[v]"' . $value_label . '>' . $field . '</select>';
        } elseif ($type === 'date') {
            $timed = !empty($column['options']['time']);
            $iso = ws_wtable_query_screen_value($column, $value, array(), defined('DATE_FORMAT') ? (string) DATE_FORMAT : '');
            $shown = preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $iso) ? ($timed ? str_replace(' ', 'T', (strlen($iso) === 10) ? $iso . ' 00:00' : $iso) : substr($iso, 0, 10)) : '';
            $field = '<input type="' . ($timed ? 'datetime-local' : 'date') . '" class="form-control form-control-sm" name="' . $name . '[v]" value="' . h($shown) . '"' . $value_label . ' ' . ($timed ? 'data-pg-datetime' : 'data-pg-date') . ' autocomplete="off">';
        } else {
            $field = '<input type="text" class="form-control form-control-sm" name="' . $name . '[v]" value="' . h($value) . '"' . $value_label
                . (in_array($type, array('number', 'money', 'formula'), true) ? ' inputmode="decimal"' : '') . ' placeholder="' . h(lang('Value')) . '">';
        }

        $remove = '';

        if ($place < count($typed)) {
            $kept = $screen;
            unset($kept['typed'][$place]);
            $params = ws_wtable_query_params($kept) + ($totals_all ? array('totals' => 'all') : array());
            $remove = '<a class="btn btn-sm btn-text theme-secondary" href="' . h($table_url . (empty($params) ? '' : '&' . http_build_query($params))) . '#ws-wt-rows" title="' . h(lang('Remove condition')) . '" aria-label="' . h(lang('Remove condition')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></a>';
        }

        $out .= '<div class="d-flex flex-wrap align-items-center gap-2 mb-2">'
            . '<select class="form-control form-control-sm w-auto" name="' . $name . '[c]" aria-label="' . h(lang('Column')) . '">' . $choices . '</select>'
            . '<select class="form-control form-control-sm w-auto" name="' . $name . '[o]" aria-label="' . h(lang('Comparison')) . '">' . $ops . '</select>'
            . '<div class="flex-grow-1" style="min-width: 10rem;">' . $field . '</div>' . $remove . '</div>';
    }

    $joins = '';

    foreach (array('all' => lang('Every condition holds'), 'any' => lang('Any condition holds')) as $key => $label) {
        $joins .= '<option value="' . $key . '"' . (($key === (string) $screen['join']) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    $sorts = '<option value="0">' . h(lang('Table order')) . '</option>';

    foreach ($columns as $column_id => $item) {
        $sorts .= '<option value="' . (int) $column_id . '"' . (((int) $column_id === (int) $screen['sort']) ? ' selected' : '') . '>' . h($item['name']) . '</option>';
    }

    $dirs = '';

    foreach (array('asc' => lang('Ascending'), 'desc' => lang('Descending')) as $key => $label) {
        $dirs .= '<option value="' . $key . '"' . (($key === (string) $screen['dir']) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    $active = !empty($typed) || ((int) $screen['sort'] > 0) || ((string) $screen['dir'] === 'desc');
    return '<form method="get" action="' . h($self_url) . '#ws-wt-rows" class="ws-wt-query border-bottom pb-3 mb-4" role="search" aria-label="' . h(lang('Filter and sort rows')) . '">
        <input type="hidden" name="id" value="' . (int) $table_id . '">' . ($totals_all ? '<input type="hidden" name="totals" value="all">' : '') . '
        ' . $out . '
        <div class="d-flex flex-wrap align-items-center gap-2">
            ' . ((count($typed) > 0) ? '<select class="form-control form-control-sm w-auto" name="join" aria-label="' . h(lang('How the conditions join')) . '">' . $joins . '</select>' : '') . '
            <label class="small fg-secondary sm:ms-auto" for="ws-wt-query-sort">' . h(lang('Sort by')) . '</label>
            <select class="form-control form-control-sm w-auto" name="sort" id="ws-wt-query-sort">' . $sorts . '</select>
            <select class="form-control form-control-sm w-auto" name="dir" aria-label="' . h(lang('Direction')) . '">' . $dirs . '</select>
            <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-4"><i class="bi bi-funnel me-1" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Apply')) . '</span></button>
            ' . ($active ? '<a class="btn btn-sm btn-text theme-secondary" href="' . h($table_url . ($totals_all ? '&totals=all' : '')) . '#ws-wt-rows">' . h(lang('Clear')) . '</a>' : '') . '
        </div>'
        . (($refusal !== '') ? '<div class="alert theme-warning small py-3 mt-3 mb-0" role="alert">' . h($refusal) . ' ' . h(lang('The rows show unfiltered.')) . '</div>' : '') . '
    </form>';
}
