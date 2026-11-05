<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Ship Date Adjustments list (view_ship_date_adjustments.php) and
 * its quick edit: every list value the adjustment screen
 * (edit_ship_date_adjustment.php) changes is a field - the zip code prefix,
 * the shipping method and the adjustment - checked by that screen's own
 * rules (includes/forms/ship_date_adjustment_form.php) and written by its
 * save (pg_store_ship_date_adjustment_save()). Created and Last Modified
 * stay text.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/ship_date_adjustment_form.php and includes/fn/list_table.php.
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
 * The largest number of days the screen's three character box takes.
 */
const PG_SHIP_DATE_ADJUSTMENT_QUICK_DAYS_MAX = 999;

/**
 * The shipping methods the screen's pick list offers, as id => label: the
 * name, its code in brackets when it has one (plain text, the driver writes
 * it as text).
 *
 * @param array $shipping_methods rows with id, name and code
 * @return array
 */
function pg_ship_date_adjustment_quick_method_options(array $shipping_methods)
{
    $options = array();

    foreach ($shipping_methods as $shipping_method) {
        $label = (string) $shipping_method['name'];

        if ((string) $shipping_method['code'] !== '') {
            $label .= ' (' . $shipping_method['code'] . ')';
        }

        $options[(string) (int) $shipping_method['id']] = $label;
    }

    return $options;
}

/**
 * The quick edit fields. The prefix holds the screen's three characters;
 * the adjustment is the stored signed number of days, negative for earlier,
 * so one box holds both the screen's days and its direction.
 *
 * @param array $shipping_methods rows with id, name and code, in the
 *                                screen's order
 * @return array field => definition
 */
function pg_ship_date_adjustment_quick_edit_columns(array $shipping_methods)
{
    return array(
        'zip_code_prefix' => array('type' => 'text', 'label' => lang('Zip Code Prefix'), 'max' => 3, 'required' => true),
        'shipping_method_id' => array('type' => 'select', 'label' => lang('Shipping Method'), 'options' => pg_ship_date_adjustment_quick_method_options($shipping_methods)),
        'adjustment' => array('type' => 'integer', 'label' => lang('Adjustment in days (negative is earlier)'), 'min' => -PG_SHIP_DATE_ADJUSTMENT_QUICK_DAYS_MAX, 'max' => PG_SHIP_DATE_ADJUSTMENT_QUICK_DAYS_MAX),
    );
}

/**
 * The shipping methods, in the screen's pick list order.
 *
 * @return array rows with id, name and code
 */
function pg_ship_date_adjustment_quick_methods()
{
    return (array) db_items("SELECT id, name, code FROM shipping_methods ORDER BY name ASC, code ASC");
}

/**
 * The list's columns; each one the screen changes is editable.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_ship_date_adjustment_list_columns($edit)
{
    $columns = array(
        'zip_code_prefix' => array('heading' => lang('Zip Code Prefix'), 'editable' => 'text'),
        'shipping_method' => array('heading' => lang('Shipping Method'), 'editable' => 'select'),
        'adjustment' => array('heading' => lang('Adjustment'), 'editable' => 'integer'),
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
 * The list's query, without its ORDER BY.
 *
 * @param int $id one adjustment only, 0 for all
 * @return string
 */
function pg_ship_date_adjustment_list_sql($id = 0)
{
    return "SELECT
            ship_date_adjustments.id,
            ship_date_adjustments.zip_code_prefix,
            ship_date_adjustments.shipping_method_id,
            shipping_methods.name AS shipping_method_name,
            shipping_methods.code AS shipping_method_code,
            ship_date_adjustments.adjustment,
            created_user.user_username AS created_username,
            ship_date_adjustments.created_timestamp,
            last_modified_user.user_username AS last_modified_username,
            ship_date_adjustments.last_modified_timestamp
        FROM ship_date_adjustments
        LEFT JOIN shipping_methods ON ship_date_adjustments.shipping_method_id = shipping_methods.id
        LEFT JOIN user AS created_user ON ship_date_adjustments.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON ship_date_adjustments.last_modified_user_id = last_modified_user.user_id"
        . (((int) $id > 0) ? " WHERE ship_date_adjustments.id = '" . (int) $id . "'" : '');
}

/**
 * The adjustment as the list shows it: "3 days earlier", "1 day later".
 *
 * @param mixed $adjustment signed days
 * @return string
 */
function pg_ship_date_adjustment_list_adjustment_text($adjustment)
{
    $days = abs((int) $adjustment);
    $suffix = ($days > 1) ? 's' : '';
    if ((int) $adjustment < 0) {
        return lang(array('string' => '{var:1} day{suffix} earlier', 'vars' => array($days), 'suffix' => $suffix));
    }

    return lang(array('string' => '{var:1} day{suffix} later', 'vars' => array($days), 'suffix' => $suffix));
}

/**
 * An adjustment's cells, drawn the one way the list and the answer to an
 * edit both use. A shipping method cell whose stored method is not among
 * the choices (deleted meanwhile) stays text: the select could not show it.
 *
 * @param array $item       pg_ship_date_adjustment_list_sql() row
 * @param array $quick_edit pg_ship_date_adjustment_quick_edit_columns(), empty without quick edit
 * @return array column => pg_list_cell()
 */
function pg_ship_date_adjustment_list_cells(array $item, array $quick_edit)
{
    $id = (int) $item['id'];
    $method_id = (string) (int) ($item['shipping_method_id'] ?? 0);

    $attributes = function ($field, $value, array $more = array()) use ($quick_edit, $id) {
        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, $value);
    };

    $method_editable = isset($quick_edit['shipping_method_id']['options'][$method_id]);

    $output_shipping_method = h($item['shipping_method_name']);

    if ((string) $item['shipping_method_code'] !== '') {
        $output_shipping_method .= ' (' . h($item['shipping_method_code']) . ')';
    }

    return array(
        'zip_code_prefix' => pg_list_cell('<a href="edit_ship_date_adjustment.php?id=' . $id . '" data-pg-open>' . h($item['zip_code_prefix']) . '</a>', 'align-middle', $attributes('zip_code_prefix', (string) $item['zip_code_prefix'])),
        'shipping_method' => pg_list_cell($output_shipping_method, 'align-middle', $method_editable ? $attributes('shipping_method_id', $method_id) : array()),
        'adjustment' => pg_list_cell(pg_ship_date_adjustment_list_adjustment_text($item['adjustment']), 'align-middle', $attributes('adjustment', (string) (int) $item['adjustment'], array('data-pg-sort-value' => (int) $item['adjustment']))),
        'created' => pg_list_table_when_cell($item['created_timestamp'], (string) $item['created_username']),
        'last_modified' => pg_list_table_when_cell($item['last_modified_timestamp'], (string) $item['last_modified_username']),
    );
}

/**
 * The adjustment's three values with the edited one put in, as the save
 * writes them. Pure.
 *
 * @param array  $item  pg_ship_date_adjustment_list_sql() row
 * @param string $field
 * @param mixed  $value pg_list_edit_request()'s value
 * @return array zip_code_prefix, shipping_method_id, adjustment
 */
function pg_ship_date_adjustment_quick_values(array $item, $field, $value)
{
    $values = array(
        'zip_code_prefix' => (string) $item['zip_code_prefix'],
        'shipping_method_id' => (int) $item['shipping_method_id'],
        'adjustment' => (int) $item['adjustment'],
    );

    if ($field === 'zip_code_prefix') {
        $values['zip_code_prefix'] = trim((string) $value);
    } elseif (isset($values[$field])) {
        $values[$field] = (int) $value;
    }

    return $values;
}

/**
 * The screen's messages that need no database: three characters in the
 * prefix, a number of days from 1 in either direction. Pure but for lang().
 *
 * @param array  $values pg_ship_date_adjustment_quick_values()
 * @param string $field
 * @return string the first message, the edited field's first, or ''
 */
function pg_ship_date_adjustment_quick_error(array $values, $field)
{
    $errors = array();

    if ($values['zip_code_prefix'] === '') {
        $errors['zip_code_prefix'] = lang('Zip Code Prefix is required.');
    } elseif (mb_strlen($values['zip_code_prefix']) != 3) {
        $errors['zip_code_prefix'] = lang('Sorry, the zip code prefix must contain 3 characters.');
    }

    if (!pg_ship_date_adjustment_form_days_valid((string) abs((int) $values['adjustment']))) {
        $errors['adjustment'] = lang('Please enter a valid number of days.');
    }

    if (isset($errors[$field])) {
        return (string) $errors[$field];
    }

    return $errors ? (string) reset($errors) : '';
}

/**
 * The quick edit answer from an adjustment read again: the edited cell and
 * every other cell of the row, each with the value its field opens with.
 *
 * @param array  $cells pg_ship_date_adjustment_list_cells()
 * @param string $field
 * @return array
 */
function pg_ship_date_adjustment_quick_saved(array $cells, $field)
{
    $column = ($field === 'shipping_method_id') ? 'shipping_method' : $field;

    $view = function ($cell) {
        $out = array('html' => $cell['html'], 'class' => $cell['class']);

        if (isset($cell['attributes']['data-pg-edit-value'])) {
            $out['value'] = (string) $cell['attributes']['data-pg-edit-value'];
        }

        if (isset($cell['attributes']['data-pg-sort-value'])) {
            $out['sort'] = (string) $cell['attributes']['data-pg-sort-value'];
        }

        return $out;
    };

    $answer = array('ok' => true) + $view($cells[$column]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach ($cells as $key => $cell) {
        if ($key !== $column) {
            $answer['cells'][$key] = $view($cell);
        }
    }

    return $answer;
}

/**
 * Writes one value changed on the list the way the adjustment screen saves:
 * its checks (a prefix and method pair no other adjustment uses, a method
 * that exists), then pg_store_ship_date_adjustment_save() and the screen's
 * Site Log line.
 *
 * @param int    $id
 * @param string $field a pg_ship_date_adjustment_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_ship_date_adjustment_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $item = db_item(pg_ship_date_adjustment_list_sql($id));

    if (!$item) {
        return $refuse(lang('Record not found.'));
    }

    $values = pg_ship_date_adjustment_quick_values($item, $field, $value);
    $error = pg_ship_date_adjustment_quick_error($values, $field);

    if ($error !== '') {
        return $refuse($error);
    }

    if (!pg_ship_date_adjustment_form_method_exists($values['shipping_method_id'])) {
        return $refuse(lang('Shipping Method is required.'));
    }

    if (pg_ship_date_adjustment_form_taken($values['zip_code_prefix'], $values['shipping_method_id'], (string) (int) $id)) {
        return $refuse(lang('Sorry, there is already a ship date adjustment for the zip code prefix and shipping method you selected.'));
    }

    if (!pg_store_ship_date_adjustment_save((int) $id, $values['zip_code_prefix'], $values['shipping_method_id'], $values['adjustment'], (int) $user['id'])) {
        return $refuse(lang('The value could not be saved.'));
    }

    log_activity(lang(array('string' => 'ship date adjustment ({var:1}) was modified', 'vars' => array($item['zip_code_prefix']))), $_SESSION['sessionusername'] ?? '');

    $row = db_item(pg_ship_date_adjustment_list_sql($id));

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    return pg_ship_date_adjustment_quick_saved(pg_ship_date_adjustment_list_cells($row, $quick_edit), $field);
}
