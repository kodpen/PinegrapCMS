<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Tax Zones list (view_tax_zones.php) and its quick edit: the name
 * and the rate, the values the tax zone screen (edit_tax_zone.php) changes
 * that the list shows, checked and written by that screen's own code
 * (includes/forms/tax_zone_form.php, pg_store_tax_zone_save()).
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/tax_zone_form.php and includes/fn/list_table.php.
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
 * The quick edit fields. The name is the screen's 50-character field; the
 * rate is read by parse_tax_rate() on the write, as the screen reads it.
 * Pure but for lang().
 *
 * @return array field => definition
 */
function pg_tax_zone_quick_edit_columns()
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 50, 'required' => true),
        'tax_rate' => array('type' => 'decimal', 'label' => lang('Tax Rate')),
    );
}

/**
 * The list's columns; the name and the rate are editable, Last Modified is
 * computed. The countries and states (several choices) are not columns.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_tax_zone_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'tax_rate' => array('heading' => lang('Tax Rate'), 'kind' => 'number', 'editable' => 'number'),
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
 * @param int $id one tax zone only, 0 for all
 * @return string
 */
function pg_tax_zone_list_sql($id = 0)
{
    return "SELECT
            tax_zones.id,
            tax_zones.name,
            tax_zones.tax_rate,
            user.user_username AS last_modified_username,
            tax_zones.timestamp AS last_modified_timestamp
        FROM tax_zones
        LEFT JOIN user ON tax_zones.user = user.user_id"
        . (((int) $id > 0) ? " WHERE tax_zones.id = '" . (int) $id . "'" : '');
}

/**
 * The value a field opens with: the name as stored, the rate trimmed to
 * the decimals it has (format_tax_rate()) with the panel's decimal
 * separator, so "18,5" is offered where the panel writes it so. Pure.
 *
 * @param string $field   a pg_tax_zone_quick_edit_columns() field
 * @param array  $row     pg_tax_zone_list_sql() row
 * @param string $decimal the panel's decimal separator
 * @return string
 */
function pg_tax_zone_quick_value($field, array $row, $decimal = '.')
{
    if ($field === 'tax_rate') {
        return str_replace('.', (string) $decimal, format_tax_rate($row['tax_rate'] ?? 0));
    }

    return (string) ($row[$field] ?? '');
}

/**
 * A tax zone's cells on the list, with their quick edit attributes.
 *
 * @param array  $row        pg_tax_zone_list_sql() row
 * @param array  $quick_edit field => definition, empty for none
 * @param string $decimal    the panel's decimal separator
 * @return array key => pg_list_cell()
 */
function pg_tax_zone_list_cells(array $row, array $quick_edit, $decimal = '.')
{
    $id = (int) $row['id'];

    $attributes = function ($field, array $more = array()) use ($row, $quick_edit, $id, $decimal) {
        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_tax_zone_quick_value($field, $row, $decimal));
    };

    // format_tax_rate() trims the stored rate to the decimals it has
    // (20.000 -> 20, 18.500 -> 18.5); the number keeps that many decimals in
    // the panel's separators and the template puts the sign on the side the
    // language writes it (TR "%20").
    $tax_rate = format_tax_rate($row['tax_rate']);
    $tax_rate_decimals = (strpos($tax_rate, '.') === false) ? 0 : strlen($tax_rate) - strpos($tax_rate, '.') - 1;

    return array(
        'name' => pg_list_cell('<a href="edit_tax_zone.php?id=' . $id . '" data-pg-open>' . h($row['name']) . '</a>', 'align-middle', $attributes('name')),
        'tax_rate' => pg_list_cell(h(lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number($tax_rate, $tax_rate_decimals))))), 'align-middle', $attributes('tax_rate', array('data-pg-sort-value' => $tax_rate))),
        'last_modified' => pg_list_table_when_cell($row['last_modified_timestamp'], (string) $row['last_modified_username']),
    );
}

/**
 * The quick edit answer from a tax zone read again: the edited cell, the
 * other field and Last Modified, each with the value its field opens
 * with. Pure.
 *
 * @param array  $cells pg_tax_zone_list_cells()
 * @param string $field the edited cell's column
 * @return array
 */
function pg_tax_zone_quick_saved(array $cells, $field)
{
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

    $answer = array('ok' => true) + $view($cells[$field]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach (array('name', 'tax_rate', 'last_modified') as $key) {
        if (($key !== $field) && isset($cells[$key])) {
            $answer['cells'][$key] = $view($cells[$key]);
        }
    }

    return $answer;
}

/**
 * The name and rate the screen would save for a change made on the list:
 * the stored zone with the one changed value, checked as the screen checks
 * it (pg_tax_zone_form_name_error(), parse_tax_rate()). Pure but for lang().
 *
 * @param array  $stored 'name', 'tax_rate'
 * @param string $field  'name' or 'tax_rate'
 * @param mixed  $value  pg_list_edit_request()'s value
 * @return array array('ok' => bool, 'name', 'tax_rate', 'message')
 */
function pg_tax_zone_quick_change(array $stored, $field, $value)
{
    $name = trim((string) ($stored['name'] ?? ''));
    $raw_rate = (string) ($stored['tax_rate'] ?? '');

    if ($field === 'name') {
        $name = trim((string) $value);
    } elseif ($field === 'tax_rate') {
        $raw_rate = (string) $value;
    }

    $name_error = pg_tax_zone_form_name_error($name);

    if ($name_error !== '') {
        return array('ok' => false, 'name' => $name, 'tax_rate' => null, 'message' => $name_error);
    }

    $tax_rate = parse_tax_rate($raw_rate);

    if ($tax_rate === NULL) {
        return array('ok' => false, 'name' => $name, 'tax_rate' => null, 'message' => lang('Enter a tax rate between 0 and 100.'));
    }

    return array('ok' => true, 'name' => $name, 'tax_rate' => $tax_rate, 'message' => '');
}

/**
 * Writes one value changed on the list through the tax zone screen's own
 * check and save, keeping the zone's countries and states; logs the change
 * as the screen does.
 *
 * @param int    $id
 * @param string $field a pg_tax_zone_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_tax_zone_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $id = (int) $id;
    $stored = db_item("SELECT name, tax_rate FROM tax_zones WHERE id = '" . $id . "'");

    if (!$stored) {
        return $refuse(lang('Record not found.'));
    }

    $change = pg_tax_zone_quick_change($stored, $field, $value);

    if (!$change['ok']) {
        return $refuse($change['message']);
    }

    $country_ids = db_values("SELECT country_id FROM tax_zones_countries_xref WHERE tax_zone_id = '" . $id . "'");
    $state_ids = db_values("SELECT state_id FROM tax_zones_states_xref WHERE tax_zone_id = '" . $id . "'");

    if (!pg_store_tax_zone_save($id, $change['name'], $change['tax_rate'], (array) $country_ids, (array) $state_ids, (int) $user['id'])) {
        return $refuse(lang('The value could not be saved.'));
    }

    log_activity(lang(array('string' => 'tax zone ({var:1}) was modified', 'vars' => $change['name'])), $_SESSION['sessionusername'] ?? '');

    $row = db_item(pg_tax_zone_list_sql($id));

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    return pg_tax_zone_quick_saved(pg_tax_zone_list_cells($row, $quick_edit, pg_number_separators()['decimal']), $field);
}
