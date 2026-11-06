<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Containers list (view_containers.php) and its quick edit: every
 * value the container screen (edit_container.php) changes is a field,
 * checked and written by that screen's own form code
 * (includes/forms/container_form.php). The sizes are typed in inches and
 * the weight in pounds, the units the screen stores.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/container_form.php.
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
 * The quick edit fields. The screen's own check decides what a figure may
 * be (pg_container_form_validate()), so the driver only reads its shape.
 * Pure but for lang().
 *
 * @return array field => definition
 */
function pg_container_quick_edit_columns()
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 100, 'required' => true),
        'enabled' => array('type' => 'select', 'label' => lang('Enabled'), 'options' => array('1' => lang('Yes'), '0' => lang('No'))),
        'length' => array('type' => 'decimal', 'label' => lang('Length')),
        'width' => array('type' => 'decimal', 'label' => lang('Width')),
        'height' => array('type' => 'decimal', 'label' => lang('Height')),
        'weight' => array('type' => 'decimal', 'label' => lang('Weight'), 'empty' => true),
        'cost' => array('type' => 'money', 'label' => lang('Cost'), 'empty' => true),
    );
}

/**
 * The list's columns; each one the screen changes is editable.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_container_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'enabled' => array('heading' => lang('Enabled'), 'kind' => 'check', 'editable' => 'select'),
        'length' => array('heading' => lang('Length'), 'kind' => 'number', 'editable' => 'number'),
        'width' => array('heading' => lang('Width'), 'kind' => 'number', 'editable' => 'number'),
        'height' => array('heading' => lang('Height'), 'kind' => 'number', 'editable' => 'number'),
        'weight' => array('heading' => lang('Weight'), 'kind' => 'number', 'editable' => 'number'),
        'cost' => array('heading' => lang('Cost'), 'kind' => 'money', 'editable' => 'money'),
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
 * The list's query, without its ORDER BY. The cost stays in minor units.
 *
 * @param int $id one container only, 0 for all
 * @return string
 */
function pg_container_list_sql($id = 0)
{
    return "SELECT
            containers.id,
            containers.name,
            containers.enabled,
            containers.length,
            containers.width,
            containers.height,
            containers.weight,
            containers.cost,
            created_user.user_username AS created_username,
            containers.created_timestamp,
            last_modified_user.user_username AS last_modified_username,
            containers.last_modified_timestamp
        FROM containers
        LEFT JOIN user AS created_user ON containers.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON containers.last_modified_user_id = last_modified_user.user_id"
        . (((int) $id > 0) ? " WHERE containers.id = '" . (int) $id . "'" : '');
}

/**
 * The value a container's field opens with: a figure at up to four places
 * without trailing zeros or thousands separators, in the site's decimal
 * style (the screen reads a comma like a point); the cost as an amount; a
 * blank weight or cost for none, as the screen shows them. Pure but for
 * the site's separators.
 *
 * @param string $field a pg_container_quick_edit_columns() field
 * @param array  $container containers row, cost in minor units
 * @return string
 */
function pg_container_quick_value($field, array $container)
{
    $raw = $container[$field] ?? '';

    switch ($field) {
        case 'name':
            return (string) $raw;

        case 'enabled':
            return $raw ? '1' : '0';

        case 'cost':
            return ((int) $raw > 0) ? pg_format_input_amount(((int) $raw) / 100) : '';

        case 'weight':
            if ((float) $raw <= 0) {
                return '';
            }
            // A weight goes on like a size.

        case 'length':
        case 'width':
        case 'height':
            $separators = function_exists('pg_number_separators') ? pg_number_separators() : array('decimal' => '.');
            $shown = rtrim(rtrim(number_format((float) $raw, 4, '.', ''), '0'), '.');

            return str_replace('.', (string) ($separators['decimal'] ?? '.'), $shown);
    }

    return '';
}

/**
 * The form's values for a change made on the list: the stored container as
 * the edit screen opens it, with the one changed value in its field. Pure
 * but for the site's separators.
 *
 * @param array  $container containers row, cost in minor units
 * @param string $field
 * @param mixed  $value pg_list_edit_request()'s value
 * @return array field => value
 */
function pg_container_quick_form_values(array $container, $field, $value)
{
    $values = array();

    foreach (array_keys(pg_container_quick_edit_columns()) as $key) {
        $values[$key] = pg_container_quick_value($key, $container);
    }

    // The screen posts no enabled field when the switch is off.
    $values['enabled'] = ($values['enabled'] === '1') ? '1' : '';

    if (array_key_exists($field, $values)) {
        $values[$field] = ($field === 'enabled') ? ((((string) $value) === '1') ? '1' : '') : (string) $value;
    }

    return $values;
}

/**
 * A container's cells on the list, with their quick edit attributes.
 *
 * @param array $container pg_container_list_sql() row
 * @param array $quick_edit field => definition, empty for none
 * @return array key => pg_list_cell()
 */
function pg_container_list_cells(array $container, array $quick_edit)
{
    $id = (int) $container['id'];

    $attributes = function ($field, array $more = array()) use ($container, $quick_edit, $id) {
        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_container_quick_value($field, $container));
    };

    // A measure cell: the value with its unit, empty when not set; sorted by
    // the number.
    $measure = function ($field, $unit) use ($container, $attributes) {
        $value = $container[$field];

        return pg_list_cell(($value > 0) ? pg_container_format_measure($value) . ' ' . $unit : '', 'align-middle', $attributes($field, array('data-pg-sort-value' => (float) $value)));
    };

    // The icon is hidden from screen readers; the word beside it says the state.
    $output_enabled = $container['enabled']
        ? '<i class="bi bi-check-circle" aria-hidden="true"></i><span class="visually-hidden">' . lang('Yes') . '</span>'
        : '<span class="visually-hidden">' . lang('No') . '</span>';

    $cost = ((int) $container['cost']) / 100;

    return array(
        'name' => pg_list_cell('<a href="edit_container.php?id=' . $id . '" data-pg-open>' . h($container['name']) . '</a>', 'align-middle ' . ($container['enabled'] ? 'status_enabled' : 'status_disabled'), $attributes('name')),
        'enabled' => pg_list_cell($output_enabled, 'align-middle', $attributes('enabled', array('data-pg-sort-value' => $container['enabled'] ? 1 : 0))),
        'length' => $measure('length', lang('in')),
        'width' => $measure('width', lang('in')),
        'height' => $measure('height', lang('in')),
        'weight' => $measure('weight', lang('lbs')),
        'cost' => pg_list_cell(($cost > 0) ? pg_format_money($cost, BASE_CURRENCY_SYMBOL) : '', 'align-middle', $attributes('cost', array('data-pg-sort-value' => (float) $cost))),
        'created' => pg_list_table_when_cell($container['created_timestamp'], $container['created_username'] ?? ''),
        'last_modified' => pg_list_table_when_cell($container['last_modified_timestamp'], $container['last_modified_username'] ?? ''),
    );
}

/**
 * The quick edit answer from a container read again: the edited cell and
 * every other cell of the row (the name's colour follows Enabled, Last
 * Modified follows every change), each with the value its field opens with.
 *
 * @param array  $cells pg_container_list_cells()
 * @param string $field the edited cell's column
 * @return array
 */
function pg_container_quick_saved(array $cells, $field)
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
 * Writes one value changed on the list through the container screen's own
 * check and save: name, length, width and height required, the name free
 * and at most 100 characters, the sizes above zero, every figure within
 * its column.
 *
 * @param int    $id
 * @param string $field a pg_container_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_container_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $container = db_item(pg_container_list_sql($id));

    if (!$container) {
        return $refuse(lang('Record not found.'));
    }

    $form = new liveform('container_quick_edit');
    $form->remove_form();

    foreach (pg_container_quick_form_values($container, $field, $value) as $form_field => $form_value) {
        $form->assign_field_value($form_field, $form_value);
    }

    pg_container_form_validate($form, (int) $id);

    if ($form->check_form_errors()) {
        $messages = array_values(array_filter($form->field_error_texts(), 'strlen'));
        $form->remove_form();

        return $refuse($messages ? $messages[0] : lang('The value could not be saved.'));
    }

    $saved = pg_container_form_save($form, (int) $id, (int) $user['id']);
    $name = (string) $form->get_field_value('name');
    $form->remove_form();

    if (!$saved) {
        return $refuse(lang('The value could not be saved.'));
    }

    log_activity(lang(array('string' => 'container ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername'] ?? '');

    $row = db_item(pg_container_list_sql($id));

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    return pg_container_quick_saved(pg_container_list_cells($row, $quick_edit), $field);
}
