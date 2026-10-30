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
        case 'unknown_column':
            return lang('The column was deleted meanwhile.');
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
 * the column had keeps its key, so the cells holding it keep their choice;
 * a new one gets the next free key. Empty lines are dropped.
 *
 * @param string $lines
 * @param array  $old   the column's choices (key, label, colour)
 * @return array[] key, label, color
 */
function ws_wtable_screen_status_choices($lines, $old = array())
{
    $by_label = array();
    $used = array();

    foreach ((array) $old as $choice) {
        if (is_array($choice) && isset($choice['key'], $choice['label'])) {
            $by_label[(string) $choice['label']] = $choice;
            $used[(string) $choice['key']] = true;
        }
    }

    $choices = array();
    $next = 1;

    foreach (preg_split('/\r\n|\r|\n/', (string) $lines) as $line) {
        $label = trim($line);

        if (($label === '') || isset($choices[$label])) {
            continue;
        }

        if (isset($by_label[$label])) {
            $choices[$label] = array('key' => (string) $by_label[$label]['key'], 'label' => $label, 'color' => (string) ($by_label[$label]['color'] ?? ''));
            continue;
        }

        while (isset($used['s' . $next])) {
            $next++;
        }

        $used['s' . $next] = true;
        $choices[$label] = array('key' => 's' . $next, 'label' => $label, 'color' => '');
    }

    return array_values($choices);
}

/**
 * A column's options from the column form, for ws_wtable_column_options_check().
 *
 * @param string     $type
 * @param array      $form decimals, currency, time, choices (lines), kinds (comma list), expression
 * @param array|null $old  the column when it is changed
 * @return array
 */
function ws_wtable_screen_options_from_form($type, $form, $old = null)
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

            return $options;

        case 'date':
            return array('time' => ($text('time') === '1'));

        case 'status':
            return array('choices' => ws_wtable_screen_status_choices($text('choices'), $old['options']['choices'] ?? array()));

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
    }

    return array();
}

/**
 * A cell as typed into its field, for the store's check: money and numbers
 * read the site's way (ws_wtable_screen_figure()), money to minor units
 * (rounded, never cut), a date and time from the picker's
 * text, a ticked box, a record link from kind:id. What cannot be read is
 * handed over as typed, so that the store refuses it with its own code.
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
            return str_replace('T', ' ', $raw);

        case 'person':
            return ctype_digit($raw) ? (int) $raw : $raw;

        case 'link':
            if (preg_match('/^([a-z_]{1,32})\s*[:#]\s*([0-9]{1,18})$/', strtolower($raw), $parts)) {
                return array('kind' => $parts[1], 'id' => (int) $parts[2]);
            }

            return $raw;
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
    }

    return is_scalar($value) ? (string) $value : '';
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

        case 'status':
            foreach ((array) ($options['choices'] ?? array()) as $choice) {
                if ((string) ($choice['key'] ?? '') === (string) $value) {
                    return (string) $choice['label'];
                }
            }

            return '';

        case 'link':
            return (is_array($value) && isset($value['kind'], $value['id'])) ? $value['kind'] . ' #' . (int) $value['id'] : '';
    }

    return is_scalar($value) ? (string) $value : '';
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
