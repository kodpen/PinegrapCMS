<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Menus list (view_menus.php) and its quick edit: every value the
 * menu screen (edit_menu.php) changes is a field, checked and written by
 * that screen's own form code (includes/forms/menu_form.php).
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/menu_form.php.
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
 * The pop-up positions the menu screen offers, stored value => label.
 * Pure but for lang().
 *
 * @return array
 */
function pg_menu_quick_positions()
{
    return array(
        'Top' => lang('Top'),
        'Bottom' => lang('Bottom'),
        'Left' => lang('Left'),
        'Right' => lang('Right'),
    );
}

/**
 * The quick edit fields. The name's rule and uniqueness are the screen's
 * own check (pg_menu_form_validate_name()), so the driver only reads its
 * length. Pure but for lang().
 *
 * @return array field => definition
 */
function pg_menu_quick_edit_columns()
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 100, 'required' => true),
        'effect' => array('type' => 'select', 'label' => lang('Effect'), 'options' => pg_menu_form_effect_labels()),
        'first_level_popup_position' => array('type' => 'select', 'label' => lang('First Expand Menu'), 'options' => pg_menu_quick_positions()),
        'second_level_popup_position' => array('type' => 'select', 'label' => lang('Then Expand Menu'), 'options' => pg_menu_quick_positions()),
        'class' => array('type' => 'text', 'label' => lang('Classes'), 'max' => 255),
        'active_item_class' => array('type' => 'text', 'label' => lang('Active Item Classes'), 'max' => 255),
    );
}

/**
 * The list's columns; each one the screen changes is editable.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_menu_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'effect' => array('heading' => lang('Effect'), 'editable' => 'select'),
        'first_level_popup_position' => array('heading' => lang('First Expand Menu'), 'editable' => 'select'),
        'second_level_popup_position' => array('heading' => lang('Then Expand Menu'), 'editable' => 'select'),
        'class' => array('heading' => lang('Classes'), 'editable' => 'text'),
        'active_item_class' => array('heading' => lang('Active Item Classes'), 'editable' => 'text'),
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
 * @param int $id one menu only, 0 for all
 * @return string
 */
function pg_menu_list_sql($id = 0)
{
    return "SELECT
            menus.id,
            menus.name,
            menus.effect,
            menus.first_level_popup_position,
            menus.second_level_popup_position,
            menus.class,
            menus.active_item_class,
            menus.created_timestamp,
            menus.last_modified_timestamp,
            created_user.user_username AS created_username,
            last_modified_user.user_username AS last_modified_username
        FROM menus
        LEFT JOIN user AS created_user ON menus.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON menus.last_modified_user_id = last_modified_user.user_id"
        . (((int) $id > 0) ? " WHERE menus.id = '" . (int) $id . "'" : '');
}

/**
 * A classes value as the screen's chips post it: the classes joined by
 * one space, each once. Pure.
 *
 * @param string $value
 * @return string
 */
function pg_menu_quick_classes($value)
{
    $classes = preg_split('/\s+/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);

    return implode(' ', array_values(array_unique($classes ? $classes : array())));
}

/**
 * The value a menu's field opens with: the stored value. Pure.
 *
 * @param string $field a pg_menu_quick_edit_columns() field
 * @param array  $menu  menus row
 * @return string
 */
function pg_menu_quick_value($field, array $menu)
{
    return (string) ($menu[$field] ?? '');
}

/**
 * The form's values for a change made on the list: the stored menu as the
 * edit screen opens it, with the one changed value in its field; a classes
 * value as the chips would post it. Pure.
 *
 * @param array  $menu  menus row
 * @param string $field
 * @param mixed  $value pg_list_edit_request()'s value
 * @return array field => value
 */
function pg_menu_quick_form_values(array $menu, $field, $value)
{
    $values = array();

    foreach (array_keys(pg_menu_quick_edit_columns()) as $key) {
        $values[$key] = pg_menu_quick_value($key, $menu);
    }

    if (array_key_exists($field, $values)) {
        $values[$field] = in_array($field, array('class', 'active_item_class'), true)
            ? pg_menu_quick_classes($value)
            : (string) $value;
    }

    return $values;
}

/**
 * A menu's cells on the list, with their quick edit attributes.
 *
 * @param array $menu pg_menu_list_sql() row
 * @param array $quick_edit field => definition, empty for none
 * @param string $send_to where the menu's items screen goes back to
 * @return array key => pg_list_cell()
 */
function pg_menu_list_cells(array $menu, array $quick_edit, $send_to = '')
{
    $id = (int) $menu['id'];

    $attributes = function ($field, array $more = array()) use ($menu, $quick_edit, $id) {
        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_menu_quick_value($field, $menu));
    };

    $url = 'view_menu_items.php?id=' . $id . '&from=design&send_to=' . urlencode((string) $send_to);

    // A stored value the form does not offer is shown as it is.
    $effects = pg_menu_form_effect_labels();
    $positions = pg_menu_quick_positions();
    $label = function (array $labels, $value) {
        return isset($labels[(string) $value]) ? h($labels[(string) $value]) : h((string) $value);
    };

    // The pop-up positions apply to the Pop-up effect only; for another
    // effect they are kept but muted.
    $position_class = 'align-middle' . (((string) $menu['effect'] === 'Pop-up') ? '' : ' fg-secondary');

    return array(
        'name' => pg_list_cell('<a href="' . h($url) . '" data-pg-open>' . h($menu['name']) . '</a>', 'align-middle', $attributes('name')),
        'effect' => pg_list_cell($label($effects, $menu['effect']), 'align-middle', $attributes('effect')),
        'first_level_popup_position' => pg_list_cell($label($positions, $menu['first_level_popup_position']), $position_class, $attributes('first_level_popup_position')),
        'second_level_popup_position' => pg_list_cell($label($positions, $menu['second_level_popup_position']), $position_class, $attributes('second_level_popup_position')),
        'class' => pg_list_cell(h((string) $menu['class']), 'align-middle text-break', $attributes('class')),
        'active_item_class' => pg_list_cell(h((string) $menu['active_item_class']), 'align-middle text-break', $attributes('active_item_class')),
        'created' => pg_list_table_when_cell($menu['created_timestamp'], $menu['created_username'] ?? ''),
        'last_modified' => pg_list_table_when_cell($menu['last_modified_timestamp'], $menu['last_modified_username'] ?? ''),
    );
}

/**
 * The quick edit answer from a menu read again: the edited cell and every
 * other cell of the row (the positions follow the effect, Last Modified
 * follows every change), each with the value its field opens with.
 *
 * @param array  $cells pg_menu_list_cells()
 * @param string $field the edited cell's column
 * @return array
 */
function pg_menu_quick_saved(array $cells, $field)
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
 * Writes one value changed on the list through the menu screen's own check
 * and save: the name required, at most 100 characters, the tag name rule
 * (underscores only for a name that already has one) and not used by
 * another menu; each classes list at most 255 characters.
 *
 * @param int    $id
 * @param string $field a pg_menu_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @param string $send_to where the name's link goes back to
 * @return array the answer for pg_list_edit_reply()
 */
function pg_menu_quick_update($id, $field, $value, array $user, array $quick_edit, $send_to = '')
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $menu = db_item(pg_menu_list_sql($id));

    if (!$menu) {
        return $refuse(lang('Record not found.'));
    }

    $form = new liveform('menu_quick_edit');
    $form->remove_form();

    foreach (pg_menu_quick_form_values($menu, $field, $value) as $form_field => $form_value) {
        $form->assign_field_value($form_field, $form_value);
    }

    pg_menu_form_validate_name($form, (string) (int) $id);
    pg_menu_form_validate_classes($form);

    if ($form->check_form_errors()) {
        $messages = array_values(array_filter($form->field_error_texts(), 'strlen'));
        $form->remove_form();

        return $refuse($messages ? $messages[0] : lang('The value could not be saved.'));
    }

    $saved = pg_menu_form_save($form, (int) $id, (int) $user['id']);
    $name = (string) $form->get_field_value('name');
    $form->remove_form();

    if (!$saved) {
        return $refuse(lang('The value could not be saved.'));
    }

    log_activity(lang(array('string' => 'menu ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername'] ?? '');

    $row = db_item(pg_menu_list_sql($id));

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    return pg_menu_quick_saved(pg_menu_list_cells($row, $quick_edit, $send_to), $field);
}
