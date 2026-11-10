<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what the workflow tables screen (workspace_wtables.php) turns
 * between the store and its forms: a cell as typed into a field and as
 * shown in the list, a column's options from the column form, and the
 * store's codes as sentences. Nothing here reads or writes the database;
 * the rules stay in wtables.php, wtable_rows.php and wtable_shares.php.
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
 * A store code (ws_wtable_refusal(), cell and option checks) as a sentence.
 *
 * @param string $code
 * @return string
 */
function ws_wtable_screen_error_text($code)
{
    $limits = ws_wtable_limits();

    switch ((string) $code) {
        case 'not_found':
            return lang('That table could not be found.');
        case 'forbidden':
            return lang('Only the table\'s owner may do that.');
        case 'not_ready':
            return lang('The database has not been upgraded yet. Run the software update first.');
        case 'map_empty':
            return lang('Choose at least one column to fill.');
        case 'no_rows':
            return lang('The file has a header row but no rows under it.');
        case 'name_empty':
            return lang('Give it a name.');
        case 'name_too_long':
            return lang(array('string' => 'The name is longer than {var:1} characters.', 'vars' => $limits['name']));
        case 'description_invalid':
        case 'description_too_long':
            return lang(array('string' => 'The description is longer than {var:1} characters.', 'vars' => $limits['description']));
        case 'too_many_tables':
            return lang(array('string' => 'You have {var:1} tables already, the most one person may have.', 'vars' => $limits['tables']));
        case 'too_many_columns':
            return lang(array('string' => 'A table has at most {var:1} columns.', 'vars' => $limits['columns']));
        case 'too_many_rows':
            return lang(array('string' => 'A table has at most {var:1} rows.', 'vars' => $limits['rows']));
        case 'too_many_shares':
            return lang('This table is shared with as many as it may be.');
        case 'type_unknown':
            return lang('Choose the column\'s type.');
        case 'type_fixed':
            return lang('A column\'s type cannot change once it is made.');
        case 'decimals_invalid':
            return lang('The decimals are out of range.');
        case 'currency_invalid':
            return lang('The currency is a three-letter code, such as EUR.');
        case 'choices_empty':
            return lang('A status column needs at least one choice, one a line.');
        case 'choices_too_many':
            return lang(array('string' => 'A status column has at most {var:1} choices.', 'vars' => $limits['statuses']));
        case 'choice_key_invalid':
        case 'choice_label_invalid':
        case 'choice_color_invalid':
            return lang(array('string' => 'Each choice is one line of at most {var:1} characters.', 'vars' => $limits['status_label']));
        case 'kinds_invalid':
            return lang('The record kinds are names such as contact or product, separated by commas.');
        case 'formula_empty':
            return lang('Write the formula, such as Price * Quantity.');
        case 'formula_too_long':
            return lang(array('string' => 'The formula is longer than {var:1} characters.', 'vars' => $limits['formula']));
        case 'nothing_to_change':
            return lang('Nothing was changed.');
        case 'changed_meanwhile':
            return lang('Somebody changed this row meanwhile: look at it again and repeat your change.');
        case 'revision_missing':
            return lang('The page is out of date: reload it.');
        case 'cells_invalid':
            return lang('Some cells need a look.');
        case 'share_not_found':
            return lang('That share could not be found.');
        case 'target_type_invalid':
        case 'target_id_invalid':
            return lang('Choose a colleague or a channel.');
        case 'level_invalid':
            return lang('Choose what they may do.');
        case 'channel_level':
            return lang('A channel can only be given view access.');
        case 'target_is_owner':
            return lang('The table is yours already.');
        case 'target_not_member':
            return lang('That person is not in the team.');
        case 'channel_unreadable':
            return lang('You do not read that channel.');
        // Cell codes (ws_wtable_cell_check()).
        case 'not_text':
        case 'too_long':
            return lang(array('string' => 'At most {var:1} characters.', 'vars' => $limits['text']));
        case 'not_number':
            return lang('Write a number.');
        case 'out_of_range':
            return lang('The number is too large.');
        case 'not_minor_units':
            return lang(array('string' => 'Write an amount, such as {var:1}.', 'vars' => ws_wtable_screen_figure_text('12.50')));
        case 'not_date':
            return lang('Write a date.');
        case 'not_person':
            return lang('Choose a colleague.');
        case 'not_status':
            return lang('Choose one of the column\'s choices.');
        case 'not_checkbox':
            return lang('Tick it or leave it.');
        case 'not_link':
            return lang('Write the record as kind:id, such as contact:12.');
        case 'link_kind_refused':
            return lang('The column does not take records of that kind.');
        case 'not_list':
            return lang('Write one item a line.');
        case 'list_too_long':
            return lang(array('string' => 'A list holds at most {var:1} items.', 'vars' => $limits['list_items']));
        case 'item_too_long':
            return lang(array('string' => 'Each item is at most {var:1} characters.', 'vars' => $limits['list_item']));
        case 'list_of_invalid':
            return lang('Choose what the list holds: texts or numbers.');
        // Row links (wtable_links.php).
        case 'row_table_invalid':
            return lang('Choose a table you read for the link.');
        case 'not_row':
        case 'row_not_found':
            return lang('Choose a row of the linked table: that row is not there.');
        case 'row_table_unread':
            return lang('You do not read the linked table, so you cannot choose its row.');
        case 'type_same':
            return lang('Choose another type.');
        case 'key_on_retype':
            return lang('A type change does not make a key: make the column the key in its form afterwards.');
        case 'key_column':
            return lang('This column is the key of the table: make it an ordinary column first, then change its type.');
        case 'type_not_changeable':
            return lang('Only text, number, money, date, list and checkbox columns change their type.');
        case 'cells_not_converted':
            return lang('Some cells do not fit the new type: change or empty them, then try again.');
        case 'no_conversion':
            return lang('This value has no form in the new type.');
        case 'list_many':
            return lang('A list of several items does not fit one value.');
        case 'decimals_lost':
            return lang('It has more decimals than the new column keeps.');
        case 'time_lost':
            return lang('Its time of day would be lost: choose a date with the time.');
        case 'not_saved':
            return lang('That could not be saved.');
        case 'unknown_column':
            return lang('The column was deleted meanwhile.');
        // The table's key (wtable_keys.php).
        case 'key_type':
            return lang('Only a text or number column can be the key.');
        case 'key_taken':
            return lang('Another column is already the key: take the key off it first.');
        case 'key_not_unique':
            return lang('The column cannot be the key while rows are empty in it or repeat a value.');
        case 'key_empty':
            return lang('The key column needs a value on every row.');
        case 'duplicate_key':
            return lang('Another row already has this key.');
    }

    return lang('That could not be saved.');
}

/**
 * Money in minor units as a machine figure: 1250 with 2 decimals is 12.50.
 * Worked in whole numbers, so that no float rounding shows.
 *
 * @param int $minor
 * @param int $decimals
 * @return string
 */
function ws_wtable_screen_money_text($minor, $decimals)
{
    $minor = (int) $minor;
    $decimals = max(0, (int) $decimals);
    $digits = (string) abs($minor);

    if ($decimals > 0) {
        $digits = str_pad($digits, $decimals + 1, '0', STR_PAD_LEFT);
        $digits = substr($digits, 0, -$decimals) . '.' . substr($digits, -$decimals);
    }

    return (($minor < 0) ? '-' : '') . $digits;
}

/**
 * A machine figure (-1234.5) written the way the site writes figures, with
 * the separators ws_calc_number() reads back: 1.234,5 on a Turkish site,
 * 1,234.5 elsewhere. Without grouping for a field the person edits, since a
 * lone group of three digits would be read as thousands there.
 *
 * @param string $figure
 * @param bool   $group  thousands separated
 * @return string
 */
function ws_wtable_screen_figure_text($figure, $group = true)
{
    $comma = ws_calc_decimal_comma();
    $figure = (string) $figure;
    $sign = (substr($figure, 0, 1) === '-') ? '-' : '';
    $parts = explode('.', ltrim($figure, '-'), 2);
    $whole = $parts[0];

    if ($group) {
        $whole = strrev(implode($comma ? '.' : ',', str_split(strrev($whole), 3)));
    }

    return $sign . $whole . (isset($parts[1]) ? ($comma ? ',' : '.') . $parts[1] : '');
}

/**
 * A stored number or formula result as a machine figure: at most 10
 * decimals, no trailing noughts. Text (a value the store did not check) is
 * left as it is.
 *
 * @param mixed $value
 * @return string
 */
function ws_wtable_screen_number_text($value)
{
    if (is_float($value)) {
        $text = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');

        return ($text === '-0') ? '0' : $text;
    }

    return is_scalar($value) ? (string) $value : '';
}

/**
 * A figure typed into an amount or number field, read the way the site
 * writes figures (ws_calc_number(), the reader of the workspace's sums): on
 * a Turkish site 1.250 is one thousand two hundred and fifty and 1.250,50
 * has decimals; elsewhere 1,250.50. A currency mark around it is dropped; a
 * share (5%) is no figure here.
 *
 * @param string $raw
 * @return float|null null when it is no figure
 */
function ws_wtable_screen_figure($raw)
{
    if (strpos($raw, '%') !== false) {
        return null;
    }

    return ws_calc_number($raw);
}

/**
 * The status choices of a column from the form: one label a line. A label
 * the column had keeps its key, so the cells holding it keep their choice.
 * When the number of lines is unchanged, a label changed on its own line is
 * a rename and keeps that line's key and colour. Any other new label gets a
 * key that no current choice and no row holds, so a key dropped earlier
 * never comes back with another meaning for the rows still holding it.
 * Empty and repeated lines are dropped.
 *
 * @param string $lines
 * @param array  $old   the column's choices (key, label, colour)
 * @param array  $held  the keys the table's rows hold in this column
 * @return array[] key, label, color
 */
function ws_wtable_screen_status_choices($lines, $old = array(), $held = array())
{
    $by_label = array();
    $old_list = array();
    $used = array();

    foreach ((array) $old as $choice) {
        if (is_array($choice) && isset($choice['key'], $choice['label'])) {
            $by_label[(string) $choice['label']] = $choice;
            $old_list[] = $choice;
            $used[(string) $choice['key']] = true;
        }
    }

    foreach ((array) $held as $key) {
        if (is_string($key) && ($key !== '')) {
            $used[$key] = true;
        }
    }

    $labels = array();
    $seen = array();

    foreach (preg_split('/\r\n|\r|\n/', (string) $lines) as $line) {
        $label = trim($line);

        if (($label !== '') && !isset($seen[$label])) {
            $seen[$label] = true;
            $labels[] = $label;
        }
    }

    $renames = (count($labels) === count($old_list));
    $choices = array();
    $next = 1;

    foreach ($labels as $position => $label) {
        if (isset($by_label[$label])) {
            $choices[] = array('key' => (string) $by_label[$label]['key'], 'label' => $label, 'color' => (string) ($by_label[$label]['color'] ?? ''));
            continue;
        }

        $was = $renames ? $old_list[$position] : null;

        if ($was && !isset($seen[(string) $was['label']])) {
            $choices[] = array('key' => (string) $was['key'], 'label' => $label, 'color' => (string) ($was['color'] ?? ''));
            continue;
        }

        while (isset($used['s' . $next])) {
            $next++;
        }

        $used['s' . $next] = true;
        $choices[] = array('key' => 's' . $next, 'label' => $label, 'color' => '');
    }

    return $choices;
}

/**
 * A column's options from the column form, for ws_wtable_column_options_check().
 *
 * @param string     $type
 * @param array      $form decimals, currency, time, choices (lines), kinds (comma list), expression
 * @param array|null $old  the column when it is changed
 * @param array      $held a status column's keys the table's rows hold
 * @return array
 */
function ws_wtable_screen_options_from_form($type, $form, $old = null, $held = array())
{
    $form = is_array($form) ? $form : array();
    $text = function ($key) use ($form) {
        return (isset($form[$key]) && is_string($form[$key])) ? trim($form[$key]) : '';
    };

    switch ((string) $type) {
        case 'number':
        case 'money':
            $options = array();

            if ($text('decimals') !== '') {
                $options['decimals'] = $text('decimals');
            }

            if ($type === 'money') {
                $options['currency'] = $text('currency');
            }

            if ($type === 'number') {
                $options['key'] = ($text('key') === '1');
            }

            return $options;

        case 'date':
            return array('time' => ($text('time') === '1'));

        case 'status':
            return array('choices' => ws_wtable_screen_status_choices($text('choices'), $old['options']['choices'] ?? array(), $held));

        case 'link':
            $kinds = array();

            foreach (explode(',', $text('kinds')) as $kind) {
                if (trim($kind) !== '') {
                    $kinds[] = strtolower(trim($kind));
                }
            }

            return array('kinds' => $kinds);

        case 'formula':
            return array('expression' => $text('expression'));

        case 'list':
            // What a list holds is fixed once made: a form of a made column
            // does not send it, and the store keeps the one it has.
            return ($text('of') !== '') ? array('of' => $text('of')) : array();

        case 'row':
            // So is the table a row link points at.
            return ($text('table') !== '') ? array('table' => $text('table')) : array();

        case 'text':
            return array('key' => ($text('key') === '1'));
    }

    return array();
}

/**
 * A refusal in words, the rows named when a column cannot become the key
 * (#3, #7 and 2 more). Pure but for lang().
 *
 * @param array $result a store refusal
 * @return string
 */
function ws_wtable_screen_refusal_text($result)
{
    $text = ws_wtable_screen_error_text((string) ($result['error'] ?? ''));

    if (((string) ($result['error'] ?? '') !== 'key_not_unique') || empty($result['rows'])) {
        return $text;
    }

    $rows = implode(', ', array_map(function ($label) {
        return '#' . (int) $label;
    }, (array) $result['rows']));
    $more = (int) ($result['rows_count'] ?? 0) - count((array) $result['rows']);

    return $text . ' ' . (($more > 0)
        ? lang(array('string' => 'Rows: {var:1} and {var:2} more.', 'vars' => array($rows, $more)))
        : lang(array('string' => 'Rows: {var:1}.', 'vars' => $rows)));
}

/**
 * A cell as typed into its field, for the store's check: money and numbers
 * read the site's way (ws_wtable_screen_figure()), money to minor units
 * (rounded, never cut), a date and time from the picker's
 * text, a ticked box, a record link from kind:id, a list one item a line
 * (numbers the site's way). What cannot be read is handed over as typed,
 * so that the store refuses it with its own code.
 *
 * @param array $column type, options
 * @param mixed $raw
 * @return mixed
 */
function ws_wtable_screen_cell_from_form($column, $raw)
{
    $type = (string) ($column['type'] ?? '');
    $options = (array) ($column['options'] ?? array());

    if ($type === 'checkbox') {
        return ((string) (is_scalar($raw) ? $raw : '') === '1');
    }

    if (!is_string($raw)) {
        return '';
    }

    $raw = trim($raw);

    if ($raw === '') {
        return null;
    }

    switch ($type) {
        case 'money':
            $amount = ws_wtable_screen_figure($raw);

            if ($amount === null) {
                return $raw;
            }

            $minor = round($amount * pow(10, isset($options['decimals']) ? (int) $options['decimals'] : 2));

            // Too large for an integer: the store answers out_of_range.
            return (abs($minor) < 1e15) ? (int) $minor : $minor;

        case 'number':
            $number = ws_wtable_screen_figure($raw);

            return ($number === null) ? $raw : $number;

        case 'date':
            // The native input posts 2026-10-29 or 2026-10-29T09:30, a
            // browser may add the seconds; the store keeps minutes.
            $raw = str_replace('T', ' ', $raw);

            return preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}):\d{2}$/', $raw, $parts) ? $parts[1] : $raw;

        case 'person':
            return ctype_digit($raw) ? (int) $raw : $raw;

        case 'link':
            if (preg_match('/^([a-z_]{1,32})\s*[:#]\s*([0-9]{1,18})$/', strtolower($raw), $parts)) {
                return array('kind' => $parts[1], 'id' => (int) $parts[2]);
            }

            return $raw;

        case 'list':
            $items = preg_split('/\r\n|\r|\n/', $raw);

            if (ws_wtable_list_of($column) === 'number') {
                foreach ($items as $place => $item) {
                    $number = (trim($item) === '') ? null : ws_wtable_screen_figure(trim($item));
                    $items[$place] = ($number === null) ? trim($item) : $number;
                }
            }

            return $items;
    }

    return $raw;
}

/**
 * A stored cell as its field shows it: the reverse of
 * ws_wtable_screen_cell_from_form().
 *
 * @param array $column
 * @param mixed $value
 * @return string
 */
function ws_wtable_screen_cell_to_form($column, $value)
{
    $type = (string) ($column['type'] ?? '');
    $options = (array) ($column['options'] ?? array());

    if (($value === null) || ($value === '')) {
        return '';
    }

    switch ($type) {
        case 'checkbox':
            return $value ? '1' : '';

        case 'money':
            return is_int($value) ? ws_wtable_screen_figure_text(ws_wtable_screen_money_text($value, isset($options['decimals']) ? (int) $options['decimals'] : 2), false) : '';

        case 'number':
            return ws_wtable_screen_figure_text(ws_wtable_screen_number_text($value), false);

        case 'link':
            return (is_array($value) && isset($value['kind'], $value['id'])) ? $value['kind'] . ':' . (int) $value['id'] : '';

        case 'list':
            return implode("\n", ws_wtable_screen_list_items($column, $value, false));

        case 'date':
            // The field is a native date or datetime-local input, which
            // drops any value that is not ISO.
            if (!is_string($value) || !preg_match('/^(\d{4}-\d{2}-\d{2})(?: (\d{2}:\d{2}))?$/', $value, $parts)) {
                return '';
            }

            return empty($options['time']) ? $parts[1] : $parts[1] . 'T' . (isset($parts[2]) ? $parts[2] : '00:00');
    }

    return is_scalar($value) ? (string) $value : '';
}

/**
 * A stored date cell ('2026-11-20' or '2026-11-20 14:30', the wall time
 * as typed, no time zone) as the site writes a date: 20.11.2026 14:30 day
 * first, 11/20/2026 month first, 2:30 PM on a twelve-hour clock. Anything
 * else comes back as it was. Pure.
 *
 * @param string $value
 * @param string $date_format DATE_FORMAT: month_day or day_month
 * @param string $time_format TIME_FORMAT: twelve_hours or twenty_four_hours
 * @return string
 */
function ws_wtable_screen_date_text($value, $date_format, $time_format)
{
    $value = (string) $value;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2})?$/', $value)) {
        return $value;
    }

    $timed = (strlen($value) > 10);
    $moment = DateTime::createFromFormat($timed ? '!Y-m-d H:i' : '!Y-m-d', $value, new DateTimeZone('UTC'));

    if (!$moment) {
        return $value;
    }

    $format = ($date_format === 'month_day') ? 'm/d/Y' : 'd.m.Y';

    if ($timed) {
        $format .= ' ' . (($time_format === 'twelve_hours') ? 'g:i A' : 'H:i');
    }

    return $moment->format($format);
}

/**
 * A cell as the list shows it, plain text (the screen escapes it).
 *
 * @param array $column
 * @param mixed $value
 * @param array $people user id => brief (name), for person cells
 * @return string
 */
function ws_wtable_screen_cell_text($column, $value, $people = array())
{
    $type = (string) ($column['type'] ?? '');
    $options = (array) ($column['options'] ?? array());

    if ($type === 'checkbox') {
        return $value ? '✓' : '';
    }

    if (($value === null) || ($value === '')) {
        return '';
    }

    switch ($type) {
        case 'money':
            $text = ws_wtable_screen_figure_text(ws_wtable_screen_money_text((int) $value, isset($options['decimals']) ? (int) $options['decimals'] : 2));

            return (($options['currency'] ?? '') !== '') ? $text . ' ' . $options['currency'] : $text;

        case 'number':
        case 'formula':
            return ws_wtable_screen_figure_text(ws_wtable_screen_number_text($value));

        case 'person':
            return isset($people[(int) $value]['name']) ? (string) $people[(int) $value]['name'] : '#' . (int) $value;

        case 'date':
            return is_string($value)
                ? ws_wtable_screen_date_text($value, defined('DATE_FORMAT') ? (string) DATE_FORMAT : '', defined('TIME_FORMAT') ? (string) TIME_FORMAT : '')
                : '';

        case 'status':
            foreach ((array) ($options['choices'] ?? array()) as $choice) {
                if ((string) ($choice['key'] ?? '') === (string) $value) {
                    return (string) $choice['label'];
                }
            }

            return '';

        case 'link':
            return (is_array($value) && isset($value['kind'], $value['id'])) ? $value['kind'] . ' #' . (int) $value['id'] : '';

        case 'row':
            return ws_wtable_link_cell_text($value, $people);

        case 'list':
            return implode(', ', ws_wtable_screen_list_items($column, $value));
    }

    return is_scalar($value) ? (string) $value : '';
}

/**
 * The items of a list cell as the screen writes them: a text as it is, a
 * number the site's way (grouped in the list, ungrouped in a field). A
 * value that is not a list reads as its one item. Pure but for the site's
 * separators.
 *
 * @param array $column
 * @param mixed $value
 * @param bool  $group  thousands grouped
 * @return string[]
 */
function ws_wtable_screen_list_items($column, $value, $group = true)
{
    $number = (ws_wtable_list_of($column) === 'number');
    $out = array();

    foreach ((is_array($value) ? $value : array($value)) as $item) {
        if (!is_scalar($item) || is_bool($item) || ((string) $item === '')) {
            continue;
        }

        $out[] = ($number && is_numeric($item)) ? ws_wtable_screen_figure_text(ws_wtable_screen_number_text($item), $group) : (string) $item;
    }

    return $out;
}

/**
 * A cell as the Excel download writes it (pg_xlsx_sheet_rows()): number,
 * money and formula cells as numbers (money as its figure, the currency
 * goes to the heading), a date as a date, a checkbox Yes or No, a list one
 * item a line, and the rest as the text the screen shows. Pure.
 *
 * @param array       $column
 * @param mixed       $value
 * @param array       $people user id => brief (name), for person cells
 * @param string|null $error  the formula's error code, when it failed
 * @return int|float|string|array
 */
function ws_wtable_export_cell($column, $value, $people = array(), $error = null)
{
    $type = (string) ($column['type'] ?? '');
    $options = (array) ($column['options'] ?? array());

    if ($type === 'checkbox') {
        return $value ? lang('Yes') : lang('No');
    }

    if (($type === 'formula') && ($error !== null) && ((string) $error !== '')) {
        return '#' . strtoupper((string) $error);
    }

    if (($value === null) || ($value === '')) {
        return '';
    }

    switch ($type) {
        case 'money':
            if (!is_numeric($value)) {
                return is_scalar($value) ? (string) $value : '';
            }

            $figure = ws_wtable_screen_money_text((int) $value, isset($options['decimals']) ? (int) $options['decimals'] : 2);

            return (strpos($figure, '.') === false) ? (int) $figure : (float) $figure;

        case 'number':
        case 'formula':
            if (is_int($value) || is_float($value)) {
                return $value;
            }

            return is_numeric($value) ? $value + 0 : (is_scalar($value) ? (string) $value : '');

        case 'date':
            return (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2})?$/', $value)) ? array('date' => $value) : (is_scalar($value) ? (string) $value : '');
        // One item a line, as the import reads a list back.
        case 'list':
            return implode("\n", ws_wtable_screen_list_items($column, $value, false));
    }

    return ws_wtable_screen_cell_text($column, $value, $people);
}

/**
 * A table as the Excel download's heading and rows: one heading per column
 * (a money column's currency after its name), then every row in the
 * table's order. Pure.
 *
 * @param array $columns column id => column (ws_wtable_columns())
 * @param array $rows    ws_wtable_rows()
 * @param array $people  user id => brief (name), for person cells
 * @return array headings (string[]), rows (lists of values)
 */
function ws_wtable_export_sheet($columns, $rows, $people = array())
{
    $headings = array();

    foreach ($columns as $column) {
        $currency = (string) ($column['options']['currency'] ?? '');
        $headings[] = (string) $column['name'] . ((($column['type'] === 'money') && ($currency !== '')) ? ' (' . $currency . ')' : '');
    }

    $lines = array();

    foreach ($rows as $row) {
        $line = array();
        $errors = (array) ($row['formula_errors'] ?? array());

        foreach ($columns as $column_id => $column) {
            $line[] = ws_wtable_export_cell($column, $row['cells'][$column_id] ?? null, $people, isset($errors[$column_id]) ? (string) $errors[$column_id] : null);
        }

        $lines[] = $line;
    }

    return array('headings' => $headings, 'rows' => $lines);
}

/**
 * The name a table downloads as: its name in ASCII letters, digits, dots,
 * dashes and underscores (a header cannot be trusted with more), or
 * table-<id> when nothing is left. Pure.
 *
 * @param string $name
 * @param int    $table_id
 * @return string
 */
function ws_wtable_export_file_name($name, $table_id)
{
    $ascii = strtr((string) $name, array('ç' => 'c', 'Ç' => 'C', 'ğ' => 'g', 'Ğ' => 'G', 'ı' => 'i', 'İ' => 'I', 'ö' => 'o', 'Ö' => 'O', 'ş' => 's', 'Ş' => 'S', 'ü' => 'u', 'Ü' => 'U'));
    $ascii = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $ascii), '._-');

    return (($ascii === '') ? 'table-' . (int) $table_id : substr($ascii, 0, 80)) . '.xlsx';
}

/**
 * A delete button of the tables screen: it posts through the form outside
 * the cards (a form may not sit inside a table row), asking first.
 *
 * @param array  $data    action, table and the ids the action needs
 * @param string $label
 * @param string $icon
 * @param string $confirm
 * @return string
 */
function ws_wtable_screen_action_button($data, $label, $icon, $confirm)
{
    $attributes = '';

    foreach ($data as $key => $value) {
        $attributes .= ' data-' . $key . '="' . h((string) $value) . '"';
    }

    return '<button type="button" class="btn btn-sm btn-text theme-danger ws-wt-action"' . $attributes . ' data-confirm="' . h($confirm) . '" title="' . h($label) . '" aria-label="' . h($label) . '">'
        . '<i class="bi ' . h($icon) . '" aria-hidden="true"></i></button>';
}

/**
 * A binned row in a line: the first cells that hold something, as
 * "Column: value", so the person knows which row it was. Pure.
 *
 * @param array $columns column id => column
 * @param array $cells   column id => value, as a row is read
 * @param array $people  user id => brief (name), for person cells
 * @param int   $max     the most cells shown
 * @return string plain text, '' when every cell is empty
 */
function ws_wtable_screen_bin_summary($columns, $cells, $people = array(), $max = 3)
{
    $parts = array();

    foreach ((array) $columns as $column_id => $column) {
        if (count($parts) >= max(1, (int) $max)) {
            break;
        }

        $text = trim(preg_replace('/\s+/u', ' ', ws_wtable_screen_cell_text($column, $cells[$column_id] ?? null, $people)));

        if ($text !== '') {
            $parts[] = (string) $column['name'] . ': ' . ((mb_strlen($text) > 60) ? mb_substr($text, 0, 59) . '…' : $text);
        }
    }

    return implode(' · ', $parts);
}

/**
 * What a delete asks first: how long the bin keeps what is deleted, or, before
 * the bin's step has run, that it cannot be brought back; for a row, how many
 * rows link to it and lose that link.
 *
 * @param string $what  table | row
 * @param string $name  the table's name
 * @param int    $days  the bin's days, 0 when there is no bin
 * @param int    $links the rows that link to the row (ws_wtable_link_counts())
 * @return string
 */
function ws_wtable_screen_delete_confirm($what, $name, $days, $links = 0)
{
    if ($what === 'table') {
        return ((int) $days > 0)
            ? lang(array('string' => 'Delete the table {var:1}? It stays in Deleted tables for {var:2} days, where you can restore it with its rows and shares.', 'vars' => array($name, (int) $days)))
            : lang(array('string' => 'Delete the table {var:1} with its rows and shares? It cannot be brought back.', 'vars' => $name));
    }

    $ask = ((int) $days > 0)
        ? lang(array('string' => 'Delete this row? It stays in the table\'s deleted rows for {var:1} days, where it can be restored.', 'vars' => (int) $days))
        : lang('Delete this row?');

    if ((int) $links === 1) {
        return $ask . ' ' . lang('One row links to it: that link is cleared, and a restore does not bring it back.');
    }

    return ((int) $links > 1)
        ? $ask . ' ' . lang(array('string' => '{var:1} rows link to it: those links are cleared, and a restore does not bring them back.', 'vars' => (int) $links))
        : $ask;
}

/**
 * The types a column may change into on the screen, by the form's word:
 * a date with or without its time, a list of texts or of numbers. Its own
 * type is left out; a column whose type does not change has none. Pure but
 * for lang().
 *
 * @param array $column type, options
 * @return array choice => label
 */
function ws_wtable_screen_retype_choices($column)
{
    $choices = array(
        'text'        => lang('Text'),
        'number'      => lang('Number'),
        'money'       => lang('Money'),
        'date'        => lang('Date'),
        'date_time'   => lang('Date with the time of day'),
        'list_text'   => lang('List of texts'),
        'list_number' => lang('List of numbers'),
        'checkbox'    => lang('Checkbox'),
    );
    $out = array();

    if (!in_array((string) ($column['type'] ?? ''), ws_wtable_retype_types(), true)) {
        return $out;
    }

    foreach ($choices as $choice => $label) {
        if (ws_wtable_retype_is_change($column, ws_wtable_screen_retype_input($choice, '', $column))) {
            $out[$choice] = $label;
        }
    }

    return $out;
}

/**
 * The type and options a choice of the form asks for. Decimals typed are
 * kept for a number or money; left empty, money keeps 2 and a number takes
 * those of the money column it was, else none. A money column keeps the
 * site's currency. Pure.
 *
 * @param string $choice   ws_wtable_screen_retype_choices()
 * @param string $decimals as typed
 * @param array  $column   the column as it is
 * @return array type, options
 */
function ws_wtable_screen_retype_input($choice, $decimals, $column)
{
    $words = array(
        'date_time'   => array('date', array('time' => true)),
        'list_text'   => array('list', array('of' => 'text')),
        'list_number' => array('list', array('of' => 'number')),
    );
    list($type, $options) = $words[(string) $choice] ?? array((string) $choice, array());
    $decimals = trim((string) $decimals);

    if (($type === 'number') || ($type === 'money')) {
        if ($decimals !== '') {
            $options['decimals'] = $decimals;
        } elseif (($type === 'number') && ((string) ($column['type'] ?? '') === 'money')) {
            $options['decimals'] = (int) ($column['options']['decimals'] ?? 2);
        }
    }

    return array('type' => $type, 'options' => $options);
}

/**
 * A cell that does not convert as the screen lists it: its row, in the bin
 * or not, its value and why. Pure but for lang().
 *
 * @param array $fault ws_wtable_retype_rows() fault
 * @return string
 */
function ws_wtable_screen_retype_fault_text($fault)
{
    $row = !empty($fault['bin'])
        ? lang(array('string' => 'Row #{var:1} (deleted, in the bin)', 'vars' => (int) $fault['label']))
        : lang(array('string' => 'Row #{var:1}', 'vars' => (int) $fault['label']));

    return $row . ': «' . (string) $fault['value'] . '» · ' . ws_wtable_screen_error_text((string) $fault['error']);
}
