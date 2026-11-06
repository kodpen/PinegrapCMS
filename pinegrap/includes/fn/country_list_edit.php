<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Countries list (view_countries.php) and its quick edit: every
 * value the country screen (edit_country.php) changes is a field, checked
 * with that screen's own form rules (includes/forms/country_form.php) and
 * written through its write path (pg_store_country_save()). Last Modified
 * stays text.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/country_form.php and includes/fn/list_table.php.
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
function pg_country_edit_columns()
{
    $yes_no = array('1' => lang('Yes'), '0' => lang('No'));

    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 50, 'required' => true),
        'code' => array('type' => 'text', 'label' => lang('Code'), 'max' => 50, 'required' => true),
        'zip_code_required' => array('type' => 'select', 'label' => lang('Zip Code Required'), 'options' => $yes_no),
        'transit_adjustment' => array('type' => 'integer', 'label' => lang('Extra Delivery Days'), 'min' => 0, 'max' => 999999999, 'empty' => true),
        'default' => array('type' => 'select', 'label' => lang('Default'), 'options' => $yes_no),
    );
}

/**
 * The list's columns; without quick edit none is editable.
 *
 * @param bool $edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_country_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'code' => array('heading' => lang('Code'), 'editable' => 'text'),
        'zip_code_required' => array('heading' => lang('Zip Code Required'), 'kind' => 'check', 'editable' => 'select'),
        'transit_adjustment' => array('heading' => lang('Extra Delivery Days'), 'kind' => 'number', 'editable' => 'number'),
        'default' => array('heading' => lang('Default'), 'kind' => 'check', 'editable' => 'select'),
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
 * The countries column each field writes.
 *
 * @return array field => column
 */
function pg_country_quick_column_names()
{
    return array(
        'name' => 'name',
        'code' => 'code',
        'zip_code_required' => 'zip_code_required',
        'transit_adjustment' => 'transit_adjustment_days',
        'default' => 'default_selected',
    );
}

/**
 * The value a field opens with: the stored name and code, the days as a
 * whole number, the switches as 1 or 0.
 *
 * @param array  $country a countries row
 * @param string $field
 * @return string
 */
function pg_country_quick_value($country, $field)
{
    $column = pg_country_quick_column_names()[$field] ?? '';
    $stored = $country[$column] ?? '';

    switch ($field) {
        case 'zip_code_required':
        case 'default':
            return !empty($stored) ? '1' : '0';

        case 'transit_adjustment':
            return (string) (int) $stored;
    }

    return (string) $stored;
}

/**
 * One quick edit checked the way the country screen checks the same box,
 * as the full set of values pg_store_country_save() writes: the stored
 * country with the one change (pg_country_form_values(), then
 * pg_country_form_required_errors() and pg_country_form_code_error()).
 *
 * @param array    $country a countries row
 * @param string   $field   a key of pg_country_edit_columns()
 * @param mixed    $value   as pg_list_edit_parse() read it
 * @param callable $taken   code lookup as pg_country_form_code_taken() (tests stub it)
 * @return array array('ok' => true, 'values' => name, code, zip_code_required,
 *               transit_adjustment_days, default_selected) or
 *               array('ok' => false, 'code', 'message')
 */
function pg_country_quick_change($country, $field, $value, $taken = 'pg_country_form_code_taken')
{
    $names = pg_country_quick_column_names();

    if (!isset($names[$field])) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('This column cannot be changed here.'));
    }

    $post = array();

    foreach ($names as $key => $column) {
        $post[$column] = pg_country_quick_value($country, $key);
    }

    // The stored name and code as stored, so an untouched padded value is
    // compared as the screen compares it.
    $post['name'] = (string) ($country['name'] ?? '');
    $post['code'] = (string) ($country['code'] ?? '');
    $post[$names[$field]] = (string) $value;

    $values = pg_country_form_values($post);
    $errors = pg_country_form_required_errors($values);

    if ($field === 'code') {
        $code_error = pg_country_form_code_error($values['code'], (string) ($country['code'] ?? ''), (int) ($country['id'] ?? 0), $taken);

        if (($code_error !== '') && !isset($errors['code'])) {
            $errors['code'] = $code_error;
        }
    }

    if ($errors) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) reset($errors));
    }

    $values['zip_code_required'] = ($values['zip_code_required'] === '1') ? 1 : 0;
    $values['default_selected'] = ($values['default_selected'] === '1') ? 1 : 0;
    $values['transit_adjustment_days'] = pg_country_form_transit_days($values['transit_adjustment_days']);

    return array('ok' => true, 'values' => $values);
}

/**
 * The list's query, without its ORDER BY.
 *
 * @param string $where a condition on countries, '' for all
 * @return string
 */
function pg_country_list_sql($where = '')
{
    return "SELECT
            countries.id,
            countries.name,
            countries.code,
            countries.zip_code_required,
            countries.transit_adjustment_days,
            countries.default_selected,
            user.user_username as user,
            countries.timestamp
        FROM countries
        LEFT JOIN user ON countries.user = user.user_id"
        . (($where !== '') ? "
        WHERE " . $where : '');
}

/**
 * The rows with their name in the panel's language as 'label', two rows
 * with the same name on screen carrying their code.
 *
 * @param array $rows rows of pg_country_list_sql()
 * @return array
 */
function pg_country_list_labelled(array $rows)
{
    foreach ($rows as $i => $row) {
        $rows[$i]['label'] = pg_geo_country_label($row['name'], $row['code']);
    }

    return pg_geo_label_disambiguate($rows);
}

/**
 * A country's cells, drawn the one way the list and the answer to an edit
 * both use. A cell is a field only when its column is in $quick_edit.
 *
 * @param array $country    a row of pg_country_list_labelled()
 * @param array $quick_edit pg_country_edit_columns(), empty without quick edit
 * @return array key => pg_list_cell()
 */
function pg_country_list_cells($country, array $quick_edit)
{
    $id = (int) ($country['id'] ?? 0);

    $attributes = function ($field, array $more = array()) use ($country, $quick_edit, $id) {
        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_country_quick_value($country, $field));
    };

    $check = function ($field, $on) use ($attributes) {
        $cell = pg_list_table_check_cell($on);

        return pg_list_cell($cell['html'], $cell['class'], $attributes($field, $cell['attributes'] ?? array()));
    };

    return array(
        'name' => pg_list_cell('<a href="edit_country.php?id=' . $id . '" data-pg-open>' . h((string) ($country['label'] ?? $country['name'] ?? '')) . '</a>', 'align-middle', $attributes('name')),
        'code' => pg_list_cell(h((string) ($country['code'] ?? '')), 'align-middle', $attributes('code')),
        'zip_code_required' => $check('zip_code_required', !empty($country['zip_code_required'])),
        'transit_adjustment' => pg_list_cell(h((string) ($country['transit_adjustment_days'] ?? '')), 'align-middle', $attributes('transit_adjustment', array('data-pg-sort-value' => (int) ($country['transit_adjustment_days'] ?? 0)))),
        'default' => $check('default', !empty($country['default_selected'])),
        'last_modified' => pg_list_table_when_cell($country['timestamp'] ?? 0, (string) ($country['user'] ?? '')),
    );
}

/**
 * The drawing part of one cell for the quick edit answer, with the value
 * its field opens with when it is one.
 *
 * @param array $cell pg_list_cell()
 * @return array
 */
function pg_country_quick_cell_view(array $cell)
{
    $out = array('html' => (string) $cell['html'], 'class' => (string) $cell['class']);

    if (isset($cell['attributes']['data-pg-edit-value'])) {
        $out['value'] = (string) $cell['attributes']['data-pg-edit-value'];
    }

    return $out;
}

/**
 * The quick edit answer from a country read again: the edited cell, every
 * other cell of the row (Last Modified follows every change) and, under
 * 'rows', the other countries the change redrew (the one that lost the
 * default switch).
 *
 * @param array  $cells  pg_country_list_cells()
 * @param string $field  the edited cell's column
 * @param array  $others id => pg_country_list_cells() of the other rows to redraw
 * @return array
 */
function pg_country_quick_saved(array $cells, $field, array $others = array())
{
    $answer = array('ok' => true) + pg_country_quick_cell_view($cells[$field]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach ($cells as $key => $cell) {
        if ($key !== $field) {
            $answer['cells'][$key] = pg_country_quick_cell_view($cell);
        }
    }

    foreach ($others as $id => $row) {
        foreach ($row as $key => $cell) {
            $answer['rows'][(string) (int) $id][$key] = pg_country_quick_cell_view($cell);
        }
    }

    return $answer;
}

/**
 * Write one quick edit from the list the way the country screen writes the
 * same box: the screen's checks (pg_country_quick_change()), the shared
 * write (pg_store_country_save(): one default, the last change stamped) and
 * the screen's Site Log line. The same value again writes nothing.
 *
 * @param int    $country_id
 * @param string $field      a key of $quick_edit
 * @param mixed  $value      as pg_list_edit_parse() read it
 * @param int    $user_id
 * @param array  $quick_edit pg_country_edit_columns()
 * @return array the answer for pg_list_edit_reply()
 */
function pg_country_quick_update($country_id, $field, $value, $user_id, array $quick_edit)
{
    $country_id = (int) $country_id;
    $country = db_item(pg_country_list_sql("countries.id = '" . $country_id . "'"));

    if (!$country) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('Record not found.'));
    }

    $change = pg_country_quick_change($country, $field, $value);

    if (!$change['ok']) {
        return $change;
    }

    $values = $change['values'];
    $changed = false;

    foreach ($values as $column => $new) {
        if ((string) $country[$column] !== (string) $new) {
            $changed = true;
        }
    }

    // The countries whose default switch the save takes off.
    $old_defaults = ($changed && ($values['default_selected'] === 1))
        ? db_values("SELECT id FROM countries WHERE (default_selected = 1) AND (id != '" . $country_id . "')")
        : array();

    if ($changed) {
        if (pg_store_country_save($country_id, $values, (int) $user_id) <= 0) {
            return array('ok' => false, 'code' => 'invalid', 'message' => lang('The value could not be saved.'));
        }

        log_activity(lang(array('string' => 'country ({var:1}) was modified', 'vars' => array($values['name']))), $_SESSION['sessionusername'] ?? '');
    }

    // Labelled with every row, so a name shared with another country
    // carries its code as on the list.
    $cells = array();
    $others = array();
    $redraw = array_map('intval', (array) $old_defaults);

    foreach (pg_country_list_labelled((array) db_items(pg_country_list_sql())) as $row) {
        if ((int) $row['id'] === $country_id) {
            $cells = pg_country_list_cells($row, $quick_edit);
        } elseif (in_array((int) $row['id'], $redraw, true)) {
            $others[(int) $row['id']] = pg_country_list_cells($row, $quick_edit);
        }
    }

    return pg_country_quick_saved($cells, $field, $others);
}
