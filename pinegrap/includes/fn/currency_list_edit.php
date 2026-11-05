<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Currencies list (view_currencies.php) and its quick edit: every
 * value the currency screen (edit_currency.php) changes is a field, checked
 * with that screen's own form rules (includes/forms/currency_form.php) and
 * written through its write path (pg_store_currency_save()). Created and
 * Last Modified stay text.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/currency_form.php and includes/fn/list_table.php.
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
 * The quick edit fields, keyed by the list's column. The lengths are the
 * screen's maxlength. Pure but for lang().
 *
 * @return array field => definition
 */
function pg_currency_edit_columns()
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 100, 'required' => true),
        'base' => array('type' => 'select', 'label' => lang('Base'), 'options' => array('1' => lang('Yes'), '0' => lang('No'))),
        'code' => array('type' => 'text', 'label' => lang('Code'), 'max' => 3, 'required' => true),
        'symbol' => array('type' => 'text', 'label' => lang('Symbol'), 'max' => 10),
        'exchange_rate' => array('type' => 'decimal', 'label' => lang('Exchange Rate')),
    );
}

/**
 * The list's columns; without quick edit none is editable.
 *
 * @param bool $edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_currency_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'base' => array('heading' => lang('Base'), 'kind' => 'check', 'editable' => 'select'),
        'code' => array('heading' => lang('Code'), 'editable' => 'text'),
        'symbol' => array('heading' => lang('Symbol'), 'editable' => 'text'),
        'exchange_rate' => array('heading' => lang('Exchange Rate'), 'kind' => 'number', 'editable' => 'number'),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    );

    if (!$edit) {
        foreach ($columns as $key => $column) {
            unset($columns[$key]['editable']);
        }
    }

    return $columns;
}

/**
 * Whether a currency's cell does not change on the list: the base
 * currency's rate is always 1 (the screen saves it so,
 * pg_currency_form_exchange_rate()).
 *
 * @param array  $currency a currencies row
 * @param string $field
 * @return bool
 */
function pg_currency_quick_locked($currency, $field)
{
    return ($field === 'exchange_rate') && ((int) ($currency['base'] ?? 0) === 1);
}

/**
 * The value a field opens with: the stored name, code and symbol (the
 * symbol as the HTML the screen keeps), the rate in the site's decimal
 * style with the screen's five places, the base as 1 or 0.
 *
 * @param array  $currency a currencies row
 * @param string $field
 * @return string
 */
function pg_currency_quick_value($currency, $field)
{
    switch ($field) {
        case 'base':
            return ((int) ($currency['base'] ?? 0) === 1) ? '1' : '0';

        case 'exchange_rate':
            return pg_format_input_amount($currency['exchange_rate'] ?? 0, 5);
    }

    return (string) ($currency[$field] ?? '');
}

/**
 * One quick edit checked the way the currency screen checks the same box,
 * as the full set of values pg_store_currency_save() writes: the stored
 * currency with the one change. The code is trimmed and in capitals, three
 * Latin letters and not another currency's (the caller asks
 * pg_currency_form_code_in_use()); the base cannot be switched off; a
 * currency made the base is saved with the rate 1; a non-base rate is above
 * zero and fits the column. Pure but for lang().
 *
 * @param array  $currency a currencies row
 * @param string $field    a key of pg_currency_edit_columns()
 * @param mixed  $value    as pg_list_edit_parse() read it
 * @return array array('ok' => true, 'values' => name, base, code, symbol, exchange_rate) or
 *               array('ok' => false, 'code', 'message')
 */
function pg_currency_quick_change($currency, $field, $value)
{
    $refuse = function ($message, $code = 'invalid') {
        return array('ok' => false, 'code' => $code, 'message' => (string) $message);
    };

    if (!isset(pg_currency_edit_columns()[$field]) || pg_currency_quick_locked($currency, $field)) {
        return $refuse(lang('This column cannot be changed here.'), 'locked');
    }

    $value = (string) $value;
    $values = array(
        'name' => (string) ($currency['name'] ?? ''),
        'base' => ((int) ($currency['base'] ?? 0) === 1) ? 1 : 0,
        'code' => (string) ($currency['code'] ?? ''),
        'symbol' => (string) ($currency['symbol'] ?? ''),
        'exchange_rate' => (string) ($currency['exchange_rate'] ?? ''),
    );

    switch ($field) {
        case 'name':
            if (trim($value) === '') {
                return $refuse(lang(array('string' => '{var:1} is required', 'vars' => array(lang(array('string' => '{var:1} Name', 'vars' => lang('Currency')))))));
            }

            $values['name'] = $value;
            break;

        case 'code':
            $code = pg_currency_form_code($value);

            if ($code === '') {
                return $refuse(lang(array('string' => '{var:1} is required', 'vars' => array(lang('Code')))));
            }

            $error = pg_currency_form_code_error($code);

            if ($error !== '') {
                return $refuse($error);
            }

            $values['code'] = $code;
            break;

        case 'symbol':
            $values['symbol'] = $value;
            break;

        case 'base':
            $error = pg_currency_form_base_error($values['base'], $value);

            if ($error !== '') {
                return $refuse($error);
            }

            if ($value === '1') {
                $values['base'] = 1;
                $values['exchange_rate'] = (string) pg_currency_form_exchange_rate('', 1);
            }
            break;

        case 'exchange_rate':
            $error = pg_currency_form_exchange_rate_error($value, 0);

            if ($error !== '') {
                return $refuse($error);
            }

            $values['exchange_rate'] = (string) pg_currency_form_exchange_rate($value, 0);
            break;
    }

    return array('ok' => true, 'values' => $values);
}

/**
 * The list's query, without its ORDER BY.
 *
 * @param int $id one currency only, 0 for all
 * @return string
 */
function pg_currency_list_sql($id = 0)
{
    return "SELECT
            currencies.id,
            currencies.name,
            currencies.base,
            currencies.code,
            currencies.symbol,
            currencies.exchange_rate,
            currencies.created_user_id,
            currencies.created_timestamp,
            currencies.last_modified_user_id,
            currencies.last_modified_timestamp,
            created_user.user_username as created_username,
            last_modified_user.user_username as last_modified_username
        FROM currencies
        LEFT JOIN user as created_user ON currencies.created_user_id = created_user.user_id
        LEFT JOIN user as last_modified_user ON currencies.last_modified_user_id = last_modified_user.user_id"
        . (((int) $id > 0) ? "
        WHERE currencies.id = '" . (int) $id . "'" : '');
}

/**
 * A currency's cells, drawn the one way the list and the answer to an edit
 * both use. A non-base rate also shows its reverse (1 USD = 49,95 TRY, how a
 * rate is usually quoted) against $base_code. A cell is a field only when
 * its column is in $quick_edit and the currency lets it change
 * (pg_currency_quick_locked()).
 *
 * @param array  $currency   a row of pg_currency_list_sql()
 * @param array  $quick_edit pg_currency_edit_columns(), empty without quick edit
 * @param string $base_code  the base currency's code
 * @return array key => pg_list_cell()
 */
function pg_currency_list_cells($currency, array $quick_edit, $base_code)
{
    $id = (int) ($currency['id'] ?? 0);
    $is_base = ((int) ($currency['base'] ?? 0) === 1);
    $rate = (float) ($currency['exchange_rate'] ?? 0);

    $attributes = function ($field, array $more = array()) use ($currency, $quick_edit, $id) {
        if (pg_currency_quick_locked($currency, $field)) {
            return $more;
        }

        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_currency_quick_value($currency, $field));
    };

    $output_inverse_rate = '';

    if (!$is_base && ($rate > 0)) {
        $output_inverse_rate = '<small class="d-block fg-secondary">1 ' . h((string) ($currency['code'] ?? '')) . ' = ' . pg_format_number(1 / $rate, 4) . ' ' . h((string) $base_code) . '</small>';
    }

    $base = pg_list_table_check_cell($is_base);

    return array(
        'name' => pg_list_cell('<a href="edit_currency.php?id=' . $id . '" data-pg-open>' . h(pg_currency_label((string) ($currency['name'] ?? ''))) . '</a>', 'align-middle', $attributes('name')),
        'base' => pg_list_cell($base['html'], $base['class'], $attributes('base', $base['attributes'] ?? array())),
        'code' => pg_list_cell(h((string) ($currency['code'] ?? '')), 'align-middle', $attributes('code')),
        // The symbol is stored as HTML (an entity like &#36;).
        'symbol' => pg_list_cell((string) ($currency['symbol'] ?? ''), 'align-middle', $attributes('symbol')),
        'exchange_rate' => pg_list_cell(pg_format_number($rate, 5) . $output_inverse_rate, 'align-middle', $attributes('exchange_rate', array('data-pg-sort-value' => $rate))),
        'created' => pg_list_table_when_cell($currency['created_timestamp'] ?? 0, (string) ($currency['created_username'] ?? '')),
        'last_modified' => pg_list_table_when_cell($currency['last_modified_timestamp'] ?? 0, (string) ($currency['last_modified_username'] ?? '')),
    );
}

/**
 * The drawing part of one cell for the quick edit answer, with the value
 * its field opens with when it is one.
 *
 * @param array $cell pg_list_cell()
 * @return array
 */
function pg_currency_quick_cell_view(array $cell)
{
    $out = array('html' => (string) $cell['html'], 'class' => (string) $cell['class']);

    if (isset($cell['attributes']['data-pg-edit-value'])) {
        $out['value'] = (string) $cell['attributes']['data-pg-edit-value'];
    }

    return $out;
}

/**
 * The quick edit answer from a currency read again: the edited cell, every
 * other cell of the row (a currency made the base gets the rate 1 and loses
 * its rate field, Last Modified follows every change) and, under 'rows',
 * the other currencies' cells when the base or its code changed (the old
 * base loses its mark, every reverse rate names the base).
 *
 * @param array  $cells  pg_currency_list_cells()
 * @param string $field  the edited cell's column
 * @param array  $others id => pg_currency_list_cells() of the other rows to redraw
 * @return array
 */
function pg_currency_quick_saved(array $cells, $field, array $others = array())
{
    $answer = array('ok' => true) + pg_currency_quick_cell_view($cells[$field]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach ($cells as $key => $cell) {
        if ($key !== $field) {
            $answer['cells'][$key] = pg_currency_quick_cell_view($cell);
        }
    }

    foreach ($others as $id => $row) {
        foreach ($row as $key => $cell) {
            $answer['rows'][(string) (int) $id][$key] = pg_currency_quick_cell_view($cell);
        }
    }

    return $answer;
}

/**
 * Write one quick edit from the list the way the currency screen writes the
 * same box: the screen's checks (pg_currency_quick_change(), the code not
 * another currency's), the shared write (pg_store_currency_save(): one base,
 * the last change stamped, currency.updated announced) and the screen's
 * Site Log line. The same value again writes nothing.
 *
 * @param int    $currency_id
 * @param string $field      a key of $quick_edit
 * @param mixed  $value      as pg_list_edit_parse() read it
 * @param int    $user_id
 * @param array  $quick_edit pg_currency_edit_columns()
 * @return array the answer for pg_list_edit_reply()
 */
function pg_currency_quick_update($currency_id, $field, $value, $user_id, array $quick_edit)
{
    $currency_id = (int) $currency_id;
    $currency = db_item(pg_currency_list_sql($currency_id));

    if (!$currency) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('Record not found.'));
    }

    $change = pg_currency_quick_change($currency, $field, $value);

    if (!$change['ok']) {
        return $change;
    }

    $values = $change['values'];

    if (($field === 'code') && pg_currency_form_code_in_use($values['code'], (string) $currency_id)) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('The code that you entered is already in use by another currency, please enter a different code.'));
    }

    $changed = false;

    foreach ($values as $column => $new) {
        if (($column === 'exchange_rate') ? ((float) $currency[$column] !== (float) $new) : ((string) $currency[$column] !== (string) $new)) {
            $changed = true;
        }
    }

    $was_base = ((int) $currency['base'] === 1);
    $base_code = $was_base ? (string) $currency['code'] : (string) db_value("SELECT code FROM currencies WHERE base = 1 LIMIT 1");

    if ($changed) {
        if (pg_store_currency_save($currency_id, $values, (int) $user_id) <= 0) {
            return array('ok' => false, 'code' => 'invalid', 'message' => lang('The value could not be saved.'));
        }

        log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('Currency'), $values['name']))), $_SESSION['sessionusername'] ?? '');

        $currency = db_item(pg_currency_list_sql($currency_id));
    }

    $now_base = ((int) $currency['base'] === 1);
    $others = array();

    // The base moved, or the base's code changed: every other row's mark or
    // reverse rate changes with it.
    if ($changed && ($now_base && (!$was_base || ($field === 'code')))) {
        $base_code = (string) $currency['code'];

        foreach ((array) db_items(pg_currency_list_sql() . " WHERE currencies.id != '" . $currency_id . "'") as $other) {
            $others[(int) $other['id']] = pg_currency_list_cells($other, $quick_edit, $base_code);
        }
    }

    if ($base_code === '') {
        $base_code = defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '';
    }

    return pg_currency_quick_saved(pg_currency_list_cells($currency, $quick_edit, $base_code), $field, $others);
}
