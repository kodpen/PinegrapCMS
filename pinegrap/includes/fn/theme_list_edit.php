<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Themes list (view_themes.php) and its quick edit: every one-line
 * value the theme screen (edit_theme_file.php) changes is a field, checked
 * and written by that screen's own code (includes/forms/theme_file_form.php).
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/theme_file_form.php and includes/fn/list_table_edit.php.
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
 * The quick edit fields. The name's rule and uniqueness are the screen's
 * own check (pg_theme_file_form_validate()), so the driver only reads its
 * length. Pure but for lang().
 *
 * @param array $folders folder id => label, as the screen's picker offers
 *                       them (pg_theme_quick_folder_choices())
 * @param array $folder_classes folder id => access class
 * @return array field => definition
 */
function pg_theme_quick_edit_columns(array $folders = array(), array $folder_classes = array())
{
    $yes_no = array('1' => lang('Yes'), '0' => lang('No'));

    return array(
        'name' => array('type' => 'text', 'label' => lang('File Name'), 'max' => 100, 'required' => true),
        'folder' => array('type' => 'select', 'label' => lang('Folder'), 'options' => $folders, 'option_classes' => $folder_classes),
        'activated_desktop' => array('type' => 'select', 'label' => lang('Activated for Desktop'), 'options' => $yes_no),
        'activated_mobile' => array('type' => 'select', 'label' => lang('Activated for Mobile'), 'options' => $yes_no),
    );
}

/**
 * The folders the theme screen's picker offers (select_folder()), with the
 * class of each one's access type, read once per request.
 *
 * @return array 'folders' (id => label), 'classes' (id => class)
 */
function pg_theme_quick_folder_choices()
{
    static $read = null;

    if ($read === null) {
        $folders = array();

        foreach (pg_page_quick_option_pairs(select_folder()) as $id => $label) {
            $folders[(int) $id] = $label;
        }

        $rows = db_items("SELECT folder_id, folder_access_control_type FROM folder");
        $read = array('folders' => $folders, 'classes' => pg_page_quick_folder_classes(is_array($rows) ? $rows : array(), $folders));
    }

    return $read;
}

/**
 * The list's columns; each one the screen changes on one line is editable.
 * The description is several lines and stays text.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_theme_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('File Name'), 'editable' => 'text'),
        'description' => array('heading' => lang('Description')),
        'folder' => array('heading' => lang('Folder'), 'editable' => 'select'),
        'activated_desktop' => array('heading' => lang('Activated for Desktop'), 'kind' => 'check', 'editable' => 'select'),
        'activated_mobile' => array('heading' => lang('Activated for Mobile'), 'kind' => 'check', 'editable' => 'select'),
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
 * The list's query, without its ORDER BY: the design CSS files marked as
 * themes.
 *
 * @param int $id one theme only, 0 for all
 * @return string
 */
function pg_theme_list_sql($id = 0)
{
    return "SELECT
            files.id,
            files.name,
            files.description,
            files.folder,
            files.activated_desktop_theme,
            files.activated_mobile_theme,
            files.timestamp,
            user.user_username,
            folder.folder_name
        FROM files
        LEFT JOIN user ON user.user_id = files.user
        LEFT JOIN folder ON folder.folder_id = files.folder
        WHERE
            (files.type = 'css')
            AND (files.design = '1')
            AND (files.theme = '1')"
        . (((int) $id > 0) ? " AND (files.id = '" . (int) $id . "')" : '');
}

/**
 * The value a theme's field opens with: the stored value; a switch is 1
 * or 0. Pure.
 *
 * @param string $field a pg_theme_quick_edit_columns() field
 * @param array  $theme pg_theme_list_sql() row
 * @return string
 */
function pg_theme_quick_value($field, array $theme)
{
    switch ($field) {
        case 'folder':
            return (string) (int) ($theme['folder'] ?? 0);

        case 'activated_desktop':
            return ((string) ($theme['activated_desktop_theme'] ?? '') === '1') ? '1' : '0';

        case 'activated_mobile':
            return ((string) ($theme['activated_mobile_theme'] ?? '') === '1') ? '1' : '0';
    }

    return (string) ($theme[$field] ?? '');
}

/**
 * The form's values for a change made on the list: the stored theme as the
 * edit screen opens it, with the one changed value in its field. Pure.
 *
 * @param array  $theme pg_theme_list_sql() row
 * @param string $field
 * @param mixed  $value pg_list_edit_request()'s value
 * @return array form field => value
 */
function pg_theme_quick_form_values(array $theme, $field, $value)
{
    $values = array(
        'id' => (string) (int) $theme['id'],
        'name' => (string) $theme['name'],
        'folder' => pg_theme_quick_value('folder', $theme),
        'description' => (string) ($theme['description'] ?? ''),
        'activated_desktop_theme' => pg_theme_quick_value('activated_desktop', $theme),
        'activated_mobile_theme' => pg_theme_quick_value('activated_mobile', $theme),
    );

    $form_fields = array(
        'name' => 'name',
        'folder' => 'folder',
        'activated_desktop' => 'activated_desktop_theme',
        'activated_mobile' => 'activated_mobile_theme',
    );

    if (isset($form_fields[$field])) {
        $values[$form_fields[$field]] = in_array($field, array('activated_desktop', 'activated_mobile'), true)
            ? ((((string) $value) === '1') ? '1' : '0')
            : (string) $value;
    }

    return $values;
}

/**
 * A theme's cells on the list, with their quick edit attributes.
 *
 * @param array $theme pg_theme_list_sql() row, plus 'access_control_type'
 *                     (the folder's, get_access_control_type())
 * @param array $quick_edit field => definition, empty for none
 * @return array key => pg_list_cell()
 */
function pg_theme_list_cells(array $theme, array $quick_edit)
{
    $id = (int) $theme['id'];

    $attributes = function ($field, array $more = array()) use ($theme, $quick_edit, $id) {
        // A folder the picker does not offer (one in the Recycle Bin) is
        // shown as it is, not as a select that would move the theme.
        if (($field === 'folder') && !isset($quick_edit['folder']['options'][(int) ($theme['folder'] ?? 0)])) {
            return $more;
        }

        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_theme_quick_value($field, $theme));
    };

    $check = function ($field) use ($theme, $attributes) {
        $cell = pg_list_table_check_cell(pg_theme_quick_value($field, $theme) === '1');

        return pg_list_cell($cell['html'], $cell['class'], $attributes($field, $cell['attributes'] ?? array()));
    };

    $access = (string) ($theme['access_control_type'] ?? '');

    return array(
        'name' => pg_list_cell('<a href="edit_theme_file.php?id=' . $id . '" data-pg-open>' . h($theme['name']) . '</a>', 'align-middle', $attributes('name')),
        'description' => pg_list_cell(nl2br(h((string) $theme['description']))),
        'folder' => pg_list_cell(pg_folder_list_name((string) $theme['folder_name']) . '<span class="badge fw-light fg-reset d-inline">(' . get_access_control_type_name($access) . ')</span>', 'align-middle text-wrap ' . $access, $attributes('folder')),
        'activated_desktop' => $check('activated_desktop'),
        'activated_mobile' => $check('activated_mobile'),
        'last_modified' => pg_list_table_when_cell($theme['timestamp'], $theme['user_username'] ?? ''),
    );
}

/**
 * A theme read for the list: its row with the folder's access type, or
 * null when it is not a theme the screen opens.
 *
 * @param int $id
 * @return array|null
 */
function pg_theme_list_row($id)
{
    $theme = db_item(pg_theme_list_sql($id));

    if (!$theme || pg_design_file_name_unsafe((string) $theme['name']) || ((string) $theme['name'] !== basename((string) $theme['name']))) {
        return null;
    }

    $theme['access_control_type'] = get_access_control_type($theme['folder']);

    return $theme;
}

/**
 * A cell as an answer draws it, with the value its field opens with.
 *
 * @param array $cell pg_list_cell()
 * @return array
 */
function pg_theme_quick_view(array $cell)
{
    $out = array('html' => $cell['html'], 'class' => $cell['class']);

    if (isset($cell['attributes']['data-pg-edit-value'])) {
        $out['value'] = (string) $cell['attributes']['data-pg-edit-value'];
    }

    return $out;
}

/**
 * The quick edit answer from a theme read again: the edited cell, every
 * other cell of the row (Last Modified follows every change) and, under
 * 'rows', the cells of the themes it took the place of as a device type's
 * activated theme. Pure.
 *
 * @param array  $cells  pg_theme_list_cells()
 * @param string $field  the edited cell's column
 * @param array  $others theme id => pg_theme_list_cells()
 * @return array
 */
function pg_theme_quick_saved(array $cells, $field, array $others = array())
{
    $answer = array('ok' => true) + pg_theme_quick_view($cells[$field]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach ($cells as $key => $cell) {
        if ($key !== $field) {
            $answer['cells'][$key] = pg_theme_quick_view($cell);
        }
    }

    if ($others) {
        $answer['rows'] = array();

        foreach ($others as $other_id => $other_cells) {
            foreach ($other_cells as $key => $cell) {
                $answer['rows'][(string) (int) $other_id][$key] = pg_theme_quick_view($cell);
            }
        }
    }

    return $answer;
}

/**
 * Writes one value changed on the list through the theme screen's own
 * check and save: the name required, ending in ".css" and not used by
 * another file (the file is renamed on the disk first); activating a theme
 * takes the other one off that device type and moves the pages to their
 * styles of this theme.
 *
 * @param int    $id
 * @param string $field a pg_theme_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_theme_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $theme = pg_theme_list_row($id);

    if (!$theme) {
        return $refuse(lang('Record not found.'));
    }

    $form = new liveform('theme_quick_edit');
    $form->remove_form();

    foreach (pg_theme_quick_form_values($theme, $field, $value) as $form_field => $form_value) {
        $form->assign_field_value($form_field, $form_value);
    }

    $first_error = function () use ($form) {
        $messages = array_values(array_filter($form->field_error_texts(), 'strlen'));
        $form->remove_form();

        return $messages ? $messages[0] : lang('The value could not be saved.');
    };

    pg_theme_file_form_validate($form);

    if ($form->check_form_errors()) {
        return $refuse($first_error());
    }

    $saved = pg_theme_file_form_save($form, (int) $user['id']);

    if (!$saved['ok']) {
        return $refuse($first_error());
    }

    $form->remove_form();

    $row = pg_theme_list_row($id);

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    $others = array();

    foreach ($saved['deactivated'] as $other_id) {
        $other = pg_theme_list_row($other_id);

        if ($other) {
            $others[(int) $other_id] = pg_theme_list_cells($other, $quick_edit);
        }
    }

    return pg_theme_quick_saved(pg_theme_list_cells($row, $quick_edit), $field, $others);
}
