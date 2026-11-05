<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Page Styles list (view_styles.php) and its quick edit: every
 * one-line value the custom style screen (edit_custom_style.php) changes
 * and the list shows is a field, checked and written by that screen's own
 * code (includes/forms/custom_style_form.php).
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/custom_style_form.php and includes/fn/list_table.php.
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
 * own check (pg_custom_style_form_name_refusal()), so the driver only
 * reads its length. Pure but for lang().
 *
 * @return array field => definition
 */
function pg_style_quick_edit_columns()
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 100, 'required' => true),
        'collection' => array('type' => 'select', 'label' => lang('Collection'), 'options' => array('a' => 'A', 'b' => 'B')),
        'layout_type' => array('type' => 'select', 'label' => lang('Layout Type'), 'options' => array(
            '' => lang('No override (the page type layout)'),
            'system' => lang('System'),
            'custom' => lang('Custom'),
        )),
    );
}

/**
 * The list's columns; each one the custom style screen changes on one line
 * is editable. The theme is not changed there, the counts are computed.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_style_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'folders' => array('heading' => lang('Folders Using Style'), 'kind' => 'number'),
        'pages' => array('heading' => lang('Pages Using Style'), 'kind' => 'number'),
        'theme' => array('heading' => lang('Theme')),
        'collection' => array('heading' => lang('Collection'), 'editable' => 'select'),
        'layout_type' => array('heading' => lang('Layout Type'), 'editable' => 'select'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
        'designer' => array('heading' => lang('Open in the visual designer'), 'kind' => 'actions'),
    );

    if (!$edit) {
        foreach ($columns as $key => $column) {
            unset($columns[$key]['editable']);
        }
    }

    return $columns;
}

/**
 * The list's query, without its ORDER BY: the HTML-template styles (the
 * visual designs have their own list).
 *
 * @param int $id one style only, 0 for all
 * @return string
 */
function pg_style_list_sql($id = 0)
{
    return "SELECT
            style.style_id as id,
            style.style_name as name,
            style.style_type as type,
            files.name AS theme_name,
            style.collection,
            style.layout_type,
            style.style_timestamp as last_modified_timestamp,
            user.user_username as last_modified_username
        FROM style
        LEFT JOIN files ON style.theme_id = files.id
        LEFT JOIN user ON style.style_user = user.user_id
        WHERE style.style_layout <> 'visual_designer'"
        . (((int) $id > 0) ? " AND style.style_id = '" . (int) $id . "'" : '');
}

/**
 * Whether a style's values change on the list: only a custom style, whose
 * screen is the custom style form; a system style opens in the visual
 * designer. Pure.
 *
 * @param array $style pg_style_list_sql() row
 * @return bool
 */
function pg_style_quick_cell_editable(array $style)
{
    return (string) ($style['type'] ?? '') === 'custom';
}

/**
 * The value a style's field opens with: the stored value, a pick list
 * value outside its list as the list's default (the screen opens it so).
 * Pure.
 *
 * @param string $field a pg_style_quick_edit_columns() field
 * @param array  $style pg_style_list_sql() row
 * @return string
 */
function pg_style_quick_value($field, array $style)
{
    if (in_array($field, array('collection', 'layout_type'), true)) {
        return pg_custom_style_form_choice($field, $style[$field] ?? '');
    }

    return (string) ($style[$field] ?? '');
}

/**
 * What the edit screen would post for a change made on the list: the
 * stored style as the screen opens it, with the one changed value in its
 * field; pg_custom_style_form_values() reads it. Pure.
 *
 * @param array  $stored style_name, style_code, collection, layout_type, social_networking_position
 * @param string $field
 * @param mixed  $value pg_list_edit_request()'s value
 * @return array post field => value
 */
function pg_style_quick_post(array $stored, $field, $value)
{
    $post = array(
        'name' => (string) ($stored['style_name'] ?? ''),
        'code' => (string) ($stored['style_code'] ?? ''),
        'collection' => (string) ($stored['collection'] ?? ''),
        'layout_type' => (string) ($stored['layout_type'] ?? ''),
        'social_networking_position' => (string) ($stored['social_networking_position'] ?? ''),
    );

    if (in_array($field, array('name', 'collection', 'layout_type'), true)) {
        $post[$field] = (string) $value;
    }

    return $post;
}

/**
 * A style's cells on the list, with their quick edit attributes.
 *
 * @param array $style   pg_style_list_sql() row
 * @param array $quick_edit field => definition, empty for none
 * @param int   $folders folders using the style (pg_style_usage())
 * @param int   $pages   pages using the style
 * @param bool  $designer whether the user may open it in the visual designer
 * @return array key => pg_list_cell()
 */
function pg_style_list_cells(array $style, array $quick_edit, $folders, $pages, $designer)
{
    $id = (int) $style['id'];
    $editable = pg_style_quick_cell_editable($style);

    $attributes = function ($field, array $more = array()) use ($style, $quick_edit, $id, $editable) {
        if (!$editable) {
            return $more;
        }

        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_style_quick_value($field, $style));
    };

    // A classic style edited as code opens in the visual designer, its code
    // as the shared component framing its pages; nothing changes on the site
    // until the design is saved (includes/fn/classic_style_design.php).
    $designer_button = '';
    if ($editable && $designer) {
        $designer_button = '<a class="btn btn-sm btn-text theme-secondary" title="' . h(lang('Open in the visual designer')) . '" aria-label="' . h(lang('Open in the visual designer')) . '" href="edit_system_style.php?id=' . $id . '&amp;classic=1"><i class="bi bi-magic" aria-hidden="true"></i></a>';
    }

    $type = in_array((string) $style['type'], array('custom', 'system'), true) ? (string) $style['type'] : 'custom';

    return array(
        'name' => pg_list_cell('<a href="edit_' . $type . '_style.php?id=' . $id . '" data-pg-open>' . h($style['name']) . '</a>', 'align-middle text-nowrap', $attributes('name')),
        'folders' => pg_list_cell(pg_format_number($folders, 0), 'align-middle', array('data-pg-sort-value' => (int) $folders)),
        'pages' => pg_list_cell(pg_format_number($pages, 0), 'align-middle', array('data-pg-sort-value' => (int) $pages)),
        'theme' => pg_list_cell(h((string) $style['theme_name'])),
        'collection' => pg_list_cell(h(strtoupper((string) $style['collection'])), 'align-middle text-center', $attributes('collection')),
        'layout_type' => pg_list_cell(h(pg_style_layout_type_label($style['layout_type'])), 'align-middle', $attributes('layout_type')),
        'last_modified' => pg_list_table_when_cell($style['last_modified_timestamp'], $style['last_modified_username'] ?? ''),
        'designer' => pg_list_cell($designer_button),
    );
}

/**
 * The quick edit answer from a style read again: the edited cell and the
 * cells a change redraws (the name, the pick lists, Last Modified), each
 * with the value its field opens with. The counts, the theme and the
 * designer button do not change. Pure.
 *
 * @param array  $cells pg_style_list_cells()
 * @param string $field the edited cell's column
 * @return array
 */
function pg_style_quick_saved(array $cells, $field)
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

    foreach (array('name', 'collection', 'layout_type', 'last_modified') as $key) {
        if (($key !== $field) && isset($cells[$key])) {
            $answer['cells'][$key] = $view($cells[$key]);
        }
    }

    return $answer;
}

/**
 * Writes one value changed on the list through the custom style screen's
 * own check and save: the name required, at most 100 characters and held
 * by no other style; a pick list value within its list.
 *
 * @param int    $id
 * @param string $field a pg_style_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_style_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $row = db_item(pg_style_list_sql($id));
    $stored = db_item("SELECT style_name, style_code, collection, layout_type, social_networking_position FROM style WHERE style_id = '" . (int) $id . "'");

    if (!$row || !$stored) {
        return $refuse(lang('Record not found.'));
    }

    if (!pg_style_quick_cell_editable($row)) {
        return $refuse(lang('This column cannot be changed here.'));
    }

    $values = pg_custom_style_form_values(pg_style_quick_post($stored, $field, $value), SOCIAL_NETWORKING == TRUE);

    $refusal = pg_custom_style_form_name_refusal($values['style_name'], (int) $id);

    if ($refusal !== '') {
        return $refuse($refusal);
    }

    if (!pg_custom_style_form_update((int) $id, $values, (int) $user['id'])) {
        return $refuse(lang('The value could not be saved.'));
    }

    $row = db_item(pg_style_list_sql($id));

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    return pg_style_quick_saved(pg_style_list_cells($row, $quick_edit, 0, 0, (int) $user['role'] <= 1), $field);
}
