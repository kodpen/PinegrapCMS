<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Shipping Zones list (view_zones.php) and its quick edit: every
 * list value the zone screen (edit_zone.php) changes is a field - the name
 * and the four rates - checked by that screen's own rules
 * (includes/forms/zone_form.php) and written by its save
 * (pg_store_zone_save()). The countries and states are several choices and
 * not on the list; Last Modified stays text.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/zone_form.php and includes/fn/list_table.php.
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
 * The quick edit fields. The name's length is the screen's input limit; a
 * blank rate is 0, as on the screen.
 *
 * @return array field => definition
 */
function pg_zone_quick_edit_columns()
{
    $columns = array('name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 50, 'required' => true));

    foreach (pg_zone_form_rate_labels() as $rate => $label) {
        $columns[$rate] = array('type' => 'money', 'label' => $label, 'empty' => true);
    }

    return $columns;
}

/**
 * The list's columns; each one the screen changes is editable.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_zone_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'base_rate' => array('heading' => lang('Base Rate'), 'kind' => 'money', 'editable' => 'money'),
        'primary_weight_rate' => array('heading' => lang('Primary W. Rate'), 'kind' => 'money', 'editable' => 'money'),
        'secondary_weight_rate' => array('heading' => lang('Secondary W. Rate'), 'kind' => 'money', 'editable' => 'money'),
        'item_rate' => array('heading' => lang('Item Rate'), 'kind' => 'money', 'editable' => 'money'),
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
 * The list's query, without its ORDER BY. Rates stay in minor units.
 *
 * @param int $id one zone only, 0 for all
 * @return string
 */
function pg_zone_list_sql($id = 0)
{
    return "SELECT
            zones.id,
            zones.name,
            zones.base_rate,
            zones.primary_weight_rate,
            zones.secondary_weight_rate,
            zones.item_rate,
            user.user_username AS user,
            zones.timestamp
        FROM zones
        LEFT JOIN user ON zones.user = user.user_id"
        . (((int) $id > 0) ? " WHERE zones.id = '" . (int) $id . "'" : '');
}

/**
 * The value a field's input opens with: the name, or a rate as the screen's
 * box shows it.
 *
 * @param string $field
 * @param array  $zone  pg_zone_list_sql() row
 * @return string
 */
function pg_zone_quick_value($field, array $zone)
{
    if ($field === 'name') {
        return (string) $zone['name'];
    }

    return pg_format_input_amount(((int) $zone[$field]) / 100);
}

/**
 * A zone's cells, drawn the one way the list and the answer to an edit both
 * use.
 *
 * @param array $zone       pg_zone_list_sql() row
 * @param array $quick_edit pg_zone_quick_edit_columns(), empty without quick edit
 * @return array column => pg_list_cell()
 */
function pg_zone_list_cells(array $zone, array $quick_edit)
{
    $id = (int) $zone['id'];

    $attributes = function ($field, array $more = array()) use ($zone, $quick_edit, $id) {
        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_zone_quick_value($field, $zone));
    };

    $cells = array(
        'name' => pg_list_cell('<a href="edit_zone.php?id=' . $id . '" data-pg-open>' . h($zone['name']) . '</a>', 'align-middle', $attributes('name')),
    );

    foreach (pg_store_zone_rate_columns() as $rate) {
        $cells[$rate] = pg_list_cell(prepare_amount($zone[$rate] / 100), 'align-middle', $attributes($rate, array('data-pg-sort-value' => (int) $zone[$rate])));
    }

    $cells['last_modified'] = pg_list_table_when_cell($zone['timestamp'], (string) $zone['user']);

    return $cells;
}

/**
 * The zone's name and the edited value, as the screen's checks read a post:
 * a rate as typed. The rates not edited are already stored in minor units
 * and are not read again. Pure.
 *
 * @param array  $zone  pg_zone_list_sql() row
 * @param string $field
 * @param mixed  $value pg_list_edit_request()'s value
 * @return array 'name' and, for a rate, that rate as typed
 */
function pg_zone_quick_values(array $zone, $field, $value)
{
    $values = array('name' => (string) $zone['name']);
    $values[$field] = ($field === 'name') ? trim((string) $value) : (string) $value;

    return $values;
}

/**
 * The columns pg_store_zone_save() writes: the name and every rate in minor
 * units, the edited rate read the screen's way (pg_zone_form_rate()). Pure
 * but for the amount format.
 *
 * @param array  $zone   pg_zone_list_sql() row
 * @param array  $values pg_zone_quick_values()
 * @param string $field
 * @return array
 */
function pg_zone_quick_write(array $zone, array $values, $field)
{
    $write = array('name' => $values['name']);

    foreach (pg_store_zone_rate_columns() as $rate) {
        $write[$rate] = ($rate === $field) ? pg_zone_form_rate($values[$rate]) : (int) $zone[$rate];
    }

    return $write;
}

/**
 * The first of the screen's messages for these values, or ''.
 *
 * @param array  $values pg_zone_quick_values()
 * @param string $field
 * @return string
 */
function pg_zone_quick_error(array $values, $field)
{
    $errors = pg_zone_form_rate_errors($values);
    $name_error = pg_zone_form_name_error($values['name']);

    if ($name_error !== '') {
        $errors['name'] = $name_error;
    }

    if (isset($errors[$field])) {
        return (string) $errors[$field];
    }

    return $errors ? (string) reset($errors) : '';
}

/**
 * The quick edit answer from a zone read again: the edited cell and every
 * other cell of the row, each with the value its field opens with.
 *
 * @param array  $cells pg_zone_list_cells()
 * @param string $field
 * @return array
 */
function pg_zone_quick_saved(array $cells, $field)
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

    foreach ($cells as $key => $cell) {
        if ($key !== $field) {
            $answer['cells'][$key] = $view($cell);
        }
    }

    return $answer;
}

/**
 * Writes one value changed on the list the way the zone screen saves: its
 * checks, then pg_store_zone_save() with the zone's own countries and
 * states (zone.updated), and the screen's Site Log line.
 *
 * @param int    $id
 * @param string $field a pg_zone_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_zone_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $zone = db_item(pg_zone_list_sql($id));

    if (!$zone) {
        return $refuse(lang('Record not found.'));
    }

    $values = pg_zone_quick_values($zone, $field, $value);
    $error = pg_zone_quick_error($values, $field);

    if ($error !== '') {
        return $refuse($error);
    }

    $write = pg_zone_quick_write($zone, $values, $field);
    $countries = db_values("SELECT country_id FROM zones_countries_xref WHERE zone_id = '" . (int) $id . "'");
    $states = db_values("SELECT state_id FROM zones_states_xref WHERE zone_id = '" . (int) $id . "'");

    if (!pg_store_zone_save((int) $id, $write, (array) $countries, (array) $states, (int) $user['id'])) {
        return $refuse(lang('The value could not be saved.'));
    }

    log_activity(lang(array('string' => 'shipping zone ({var:1}) was modified', 'vars' => array($values['name']))), $_SESSION['sessionusername'] ?? '');

    $row = db_item(pg_zone_list_sql($id));

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    return pg_zone_quick_saved(pg_zone_list_cells($row, $quick_edit), $field);
}
