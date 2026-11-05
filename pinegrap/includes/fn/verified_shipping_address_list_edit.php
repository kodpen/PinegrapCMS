<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Verified Shipping Addresses list
 * (view_verified_shipping_addresses.php) and its quick edit: the company,
 * the two address lines, the city, the state and the zip code, the values
 * the address screen (edit_verified_shipping_address.php) changes, checked
 * as that screen checks them and written by its own save
 * (pg_store_verified_address_save()).
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/verified_shipping_address_form.php and
 * includes/fn/list_table.php.
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
 * The address fields in the order the screen saves them.
 *
 * @return array list of column names
 */
function pg_verified_address_quick_fields()
{
    return array('company', 'address_1', 'address_2', 'city', 'state_id', 'zip_code');
}

/**
 * The state pick list of the quick edit, as the list's state filter labels
 * it (pg_geo_state_option_labels()). Pure but for lang().
 *
 * @param array $states         rows of id, name, country_name, country_code
 * @param bool  $foreign_states whether states of a non-default country exist
 * @return array state id => label (plain text)
 */
function pg_verified_address_state_options(array $states, $foreign_states)
{
    $options = array();

    foreach (pg_geo_state_option_labels($states, $foreign_states) as $state) {
        $options[(string) $state['id']] = (string) $state['label'];
    }

    return $options;
}

/**
 * The quick edit fields: the screen's 50-character text fields (address 1,
 * city and zip code required) and the state, one of the site's states.
 * Pure but for lang().
 *
 * @param array $state_options pg_verified_address_state_options()
 * @return array field => definition
 */
function pg_verified_address_quick_edit_columns(array $state_options)
{
    return array(
        'company' => array('type' => 'text', 'label' => lang('Company'), 'max' => 50),
        'address_1' => array('type' => 'text', 'label' => lang('Address 1'), 'max' => 50, 'required' => true),
        'address_2' => array('type' => 'text', 'label' => lang('Address 2'), 'max' => 50),
        'city' => array('type' => 'text', 'label' => lang('City'), 'max' => 50, 'required' => true),
        'state' => array('type' => 'select', 'label' => lang('State'), 'options' => $state_options),
        'zip_code' => array('type' => 'text', 'label' => lang('Zip Code'), 'max' => 50, 'required' => true),
    );
}

/**
 * The list's columns; the address parts are editable, the country follows
 * the state and Created and Last Modified are computed.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_verified_address_list_columns($edit)
{
    $columns = array(
        'company' => array('heading' => lang('Company'), 'editable' => 'text'),
        'address_1' => array('heading' => lang('Address 1'), 'editable' => 'text'),
        'address_2' => array('heading' => lang('Address 2'), 'editable' => 'text'),
        'city' => array('heading' => lang('City'), 'editable' => 'text'),
        'state' => array('heading' => lang('State'), 'editable' => 'select'),
        'zip_code' => array('heading' => lang('Zip Code'), 'editable' => 'text'),
        'country' => array('heading' => lang('Country')),
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
 * @param int    $id       one address only, 0 for all
 * @param string $state_id the state filter, '[All]' or '' for every state
 * @return string
 */
function pg_verified_address_list_sql($id = 0, $state_id = '')
{
    $where = array();

    if ((int) $id > 0) {
        $where[] = "verified_shipping_addresses.id = '" . (int) $id . "'";
    }

    if (((string) $state_id !== '') && ((string) $state_id !== '[All]')) {
        $where[] = "verified_shipping_addresses.state_id = '" . escape((string) $state_id) . "'";
    }

    return "SELECT
            verified_shipping_addresses.id,
            verified_shipping_addresses.company,
            verified_shipping_addresses.address_1,
            verified_shipping_addresses.address_2,
            verified_shipping_addresses.city,
            verified_shipping_addresses.state_id,
            states.name AS state_name,
            verified_shipping_addresses.zip_code,
            countries.name AS country_name,
            countries.code AS country_code,
            created_user.user_username AS created_username,
            verified_shipping_addresses.created_timestamp,
            last_modified_user.user_username AS last_modified_username,
            verified_shipping_addresses.last_modified_timestamp
        FROM verified_shipping_addresses
        LEFT JOIN states ON verified_shipping_addresses.state_id = states.id
        LEFT JOIN countries ON states.country_id = countries.id
        LEFT JOIN user AS created_user ON verified_shipping_addresses.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON verified_shipping_addresses.last_modified_user_id = last_modified_user.user_id"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
}

/**
 * An address's cells on the list, with their quick edit attributes. The
 * state cell is a field only while its stored state is one of the choices,
 * so a field never opens on another state than the one shown. Pure but for
 * lang().
 *
 * @param array $row        pg_verified_address_list_sql() row
 * @param array $quick_edit field => definition, empty for none
 * @return array key => pg_list_cell()
 */
function pg_verified_address_list_cells(array $row, array $quick_edit)
{
    $id = (int) $row['id'];

    $cell = function ($field, $html, $value) use ($quick_edit, $id) {
        return pg_list_cell($html, 'align-middle', pg_list_edit_cell_attributes($quick_edit, $field, $id, (string) $value));
    };

    $state_id = (string) ($row['state_id'] ?? '');
    $state_fields = isset($quick_edit['state']['options'][$state_id]) ? $quick_edit : array();
    $company = (string) ($row['company'] ?? '');

    return array(
        'company' => $cell('company', '<a href="edit_verified_shipping_address.php?id=' . $id . '" data-pg-open>' . h(($company !== '') ? $company : '#' . $id) . '</a>', $company),
        'address_1' => $cell('address_1', h($row['address_1']), $row['address_1']),
        'address_2' => $cell('address_2', h($row['address_2']), $row['address_2']),
        'city' => $cell('city', h($row['city']), $row['city']),
        'state' => pg_list_cell(h($row['state_name']), 'align-middle', pg_list_edit_cell_attributes($state_fields, 'state', $id, $state_id)),
        'zip_code' => $cell('zip_code', h($row['zip_code']), $row['zip_code']),
        'country' => pg_list_cell(h(pg_geo_country_label($row['country_name'], $row['country_code']))),
        'created' => pg_list_table_when_cell($row['created_timestamp'], (string) $row['created_username']),
        'last_modified' => pg_list_table_when_cell($row['last_modified_timestamp'], (string) $row['last_modified_username']),
    );
}

/**
 * The quick edit answer from an address read again: the edited cell and
 * every other cell (the company link shows address 1's number when the
 * company is emptied, the country follows the state), each with the value
 * its field opens with. Pure.
 *
 * @param array  $cells pg_verified_address_list_cells()
 * @param string $field the edited cell's column
 * @return array
 */
function pg_verified_address_quick_saved(array $cells, $field)
{
    $view = function ($cell) {
        $out = array('html' => $cell['html'], 'class' => $cell['class']);

        if (isset($cell['attributes']['data-pg-edit-value'])) {
            $out['value'] = (string) $cell['attributes']['data-pg-edit-value'];
        }

        return $out;
    };

    $answer = array('ok' => true) + $view($cells[$field]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach ($cells as $key => $cell) {
        if ($key !== $field) {
            $answer['cells'][$key] = $view($cell);
        }
    }

    return $answer;
}

/**
 * The address the screen would save for a change made on the list: the
 * stored address with the one changed value, checked as the screen checks
 * it (pg_verified_shipping_address_form_validate(): address 1, city, state
 * and zip code required). Pure but for lang().
 *
 * @param array  $stored pg_verified_address_quick_fields() => value
 * @param string $field  a pg_verified_address_quick_edit_columns() field
 * @param mixed  $value  pg_list_edit_request()'s value
 * @return array array('ok' => bool, 'values' => column => value, 'message')
 */
function pg_verified_address_quick_change(array $stored, $field, $value)
{
    $values = array();

    foreach (pg_verified_address_quick_fields() as $column) {
        $values[$column] = (string) ($stored[$column] ?? '');
    }

    $column = ($field === 'state') ? 'state_id' : (string) $field;

    if (!array_key_exists($column, $values)) {
        return array('ok' => false, 'values' => $values, 'message' => lang('This column cannot be changed here.'));
    }

    $values[$column] = trim((string) $value);

    $required = array(
        'address_1' => lang('Address 1 is required.'),
        'city' => lang('City is required.'),
        'state_id' => lang('State is required.'),
        'zip_code' => lang('Zip Code is required.'),
    );

    if (isset($required[$column]) && (($values[$column] === '') || (($column === 'state_id') && ((int) $values[$column] < 1)))) {
        return array('ok' => false, 'values' => $values, 'message' => $required[$column]);
    }

    return array('ok' => true, 'values' => $values, 'message' => '');
}

/**
 * Writes one value changed on the list through the address screen's own
 * save, and logs the change as the screen does.
 *
 * @param int    $id
 * @param string $field a pg_verified_address_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_verified_address_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $id = (int) $id;
    $stored = db_item("SELECT " . implode(', ', pg_verified_address_quick_fields()) . " FROM verified_shipping_addresses WHERE id = '" . $id . "'");

    if (!$stored) {
        return $refuse(lang('Record not found.'));
    }

    $change = pg_verified_address_quick_change($stored, $field, $value);

    if (!$change['ok']) {
        return $refuse($change['message']);
    }

    if (!pg_store_verified_address_save($id, $change['values'], (int) $user['id'])) {
        return $refuse(lang('The value could not be saved.'));
    }

    $name = pg_verified_shipping_address_form_name($change['values']['company'], $change['values']['address_1']);

    log_activity(lang(array('string' => 'verified shipping address ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername'] ?? '');

    $row = db_item(pg_verified_address_list_sql($id));

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    return pg_verified_address_quick_saved(pg_verified_address_list_cells($row, $quick_edit), $field);
}
