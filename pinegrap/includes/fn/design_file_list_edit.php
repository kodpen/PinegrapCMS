<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Design Files list (view_design_files.php) and its quick edit:
 * every value the design file screen (edit_design_file.php)
 * changes and the list shows is a field, checked and written by that
 * screen's own code (includes/forms/design_file_form.php).
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/design_file_form.php, includes/fn/list_table.php and
 * includes/fn/list_table_edit.php.
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
 * The quick edit fields. The name's rules are the screen's own check
 * (pg_design_file_form_refusal()), so the driver only reads its length.
 * Pure but for lang().
 *
 * @param array $folders folder id => label, as the screen's picker offers
 *                       them (pg_design_file_quick_folder_choices())
 * @param array $folder_classes folder id => access class
 * @return array field => definition
 */
function pg_design_file_quick_edit_columns(array $folders = array(), array $folder_classes = array())
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 100, 'required' => true),
        'folder' => array('type' => 'select', 'label' => lang('Folder'), 'options' => $folders, 'option_classes' => $folder_classes),
        'description' => array('type' => 'textarea', 'label' => lang('Description')),
    );
}

/**
 * The folders the design file screen's picker offers (select_folder()),
 * with the class of each one's access type, read once per request.
 *
 * @return array 'folders' (id => label), 'classes' (id => class)
 */
function pg_design_file_quick_folder_choices()
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
 * The list's columns; each one the screen changes is editable (the
 * description over several lines); the type, size, thumbnail and optimized
 * state follow the file.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_design_file_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'thumbnail' => array('heading' => lang('Thumbnail'), 'kind' => 'image'),
        'type' => array('heading' => lang('Type')),
        'size' => array('heading' => lang('Size'), 'kind' => 'number'),
        'optimized' => array('heading' => lang('Optimized'), 'kind' => 'check'),
        'folder' => array('heading' => lang('Folder'), 'editable' => 'select'),
        'description' => array('heading' => lang('Description'), 'editable' => 'textarea'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
        'actions' => array('heading' => lang('Action'), 'kind' => 'actions'),
    );

    if (!$edit) {
        foreach ($columns as $key => $column) {
            unset($columns[$key]['editable']);
        }
    }

    return $columns;
}

/**
 * The list's query, without its ORDER BY: the design files outside the
 * Recycle Bin.
 *
 * @param int $id one file only, 0 for all
 * @return string
 */
function pg_design_file_list_sql($id = 0)
{
    return "SELECT
            files.id,
            files.name,
            files.folder,
            folder.folder_name,
            folder.folder_archived,
            files.description,
            files.type,
            files.size,
            files.optimized,
            files.theme,
            user.user_username,
            files.timestamp,
            files.design
        FROM files
        LEFT JOIN folder ON files.folder = folder.folder_id
        LEFT JOIN user ON files.user = user.user_id
        WHERE (files.design = '1')" . pg_design_files_bin_where()
        . (((int) $id > 0) ? " AND (files.id = '" . (int) $id . "')" : '');
}

/**
 * The value a file's field opens with: the stored name, the folder id.
 * Pure.
 *
 * @param string $field a pg_design_file_quick_edit_columns() field
 * @param array  $file  pg_design_file_list_sql() row
 * @return string
 */
function pg_design_file_quick_value($field, array $file)
{
    if ($field === 'folder') {
        return (string) (int) ($file['folder'] ?? 0);
    }

    return (string) ($file[$field] ?? '');
}

/**
 * What the edit screen would post for a change made on the list: the
 * stored file as the screen opens it (the design switch on, the Theme
 * switch as stored), with the one changed value in its field. Pure.
 *
 * @param array  $file  pg_design_file_list_sql() row
 * @param string $field
 * @param mixed  $value pg_list_edit_request()'s value
 * @return array post field => value
 */
function pg_design_file_quick_post(array $file, $field, $value)
{
    $post = array(
        'name' => (string) ($file['name'] ?? ''),
        'folder' => pg_design_file_quick_value('folder', $file),
        'description' => (string) ($file['description'] ?? ''),
        'design' => '1',
        'theme' => ((string) ($file['theme'] ?? '') === '1') ? '1' : '',
    );

    if (in_array($field, array('name', 'folder'), true)) {
        $post[$field] = trim((string) $value);
    } elseif ($field === 'description') {
        $post['description'] = (string) $value;
    }

    return $post;
}

/**
 * A design file's cells on the list, with their quick edit attributes, and
 * whether it is an image the image editor opens.
 *
 * @param array  $file  pg_design_file_list_sql() row
 * @param array  $quick_edit field => definition, empty for none
 * @param bool   $optimize_percent whether the optimize button shows how much it saves
 * @param string $send_to where the name's link comes back to
 * @return array 'cells' (key => pg_list_cell()), 'image' (bool)
 */
function pg_design_file_list_cells(array $file, array $quick_edit, $optimize_percent, $send_to)
{
    $id = (int) $file['id'];
    $file_type = pg_file_name_extension($file['name']);
    $is_image = in_array($file_type, array('bmp', 'gif', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'webp'), true);
    $editor_image = false;
    $output_optimize_button = '';
    $output_edit_image_button = '';

    $attributes = function ($field, array $more = array()) use ($file, $quick_edit, $id) {
        // A folder the picker does not offer is shown as it is, not as a
        // select that would move the file.
        if (($field === 'folder') && !isset($quick_edit['folder']['options'][(int) ($file['folder'] ?? 0)])) {
            return $more;
        }

        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_design_file_quick_value($field, $file));
    };

    if ($is_image && ($file_type !== 'tif')) {
        if (!$file['optimized'] && (extension_loaded('imagick') || extension_loaded('gd'))) {
            $optimize_percent_html = $optimize_percent
                ? '<span class="ps-1 fs-smaller">' . h(lang(array('string' => '{var:1}%', 'vars' => calculate_optimizable_percent(FILE_DIRECTORY_PATH . '/' . $file['name'])))) . '</span>'
                : '';
            $output_optimize_button = '<a class="btn btn-sm btn-text theme-success" title="' . h(lang('Optimize this image')) . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/optimize.php?id=' . $id . get_token_query_string_field() . h('&send_to=' . urlencode(PATH . SOFTWARE_DIRECTORY . '/view_design_files.php')) . '"><i class="bi bi-fast-forward-circle" aria-hidden="true"></i><span class="visually-hidden">' . h(lang('Optimize this image')) . '</span>' . $optimize_percent_html . '</a>';
        }

        $output_edit_image_button = '<button type="button" class="btn btn-sm btn-text theme-secondary" title="' . h(lang(array('string'=>'Edit this image with {var:1}','vars'=>array(lang('Image Editor'))))) . '" aria-label="' . h(lang(array('string'=>'Edit this image with {var:1}','vars'=>array(lang('Image Editor'))))) . '" data-pg-image-edit="' . h($file['name']) . '"><i class="bi bi-brush" aria-hidden="true"></i></button>';
        $editor_image = true;
    }

    $output_thumbnail = '';

    if ($is_image) {
        // The dimensions in the File Manager's format ("20 × 10 px");
        // nothing for a file that is not a readable image.
        $image_size = @getimagesize(FILE_DIRECTORY_PATH . '/' . $file['name']);
        $output_image_dimensions = ($image_size !== false) ? pg_image_dimensions_label($image_size[0], $image_size[1]) : '';
        $output_thumbnail = '<img style="width: 50px;height:50px;object-fit:cover;" title="' . h($output_image_dimensions) . '" alt="" class="img-fluid img-thumbnail" data-pg-thumb="' . h(pg_image_panel_thumb_url(PATH . encode_url_path($file['name']))) . '" />';
    }

    return array(
        'image' => $editor_image,
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_design_file.php?id=' . $id . '&amp;send_to=' . h(urlencode($send_to)) . '" data-pg-open title="' . h($file['name']) . '">' . h($file['name']) . '</a>', 'align-middle', $attributes('name')),
            'thumbnail' => pg_list_cell($output_thumbnail),
            'type' => pg_list_cell(h($file_type)),
            'size' => pg_list_cell(h(convert_bytes_to_string($file['size'], 'auto')), 'align-middle', array('data-pg-sort-value' => (int) $file['size'])),
            'optimized' => pg_list_table_check_cell($file['optimized']),
            'folder' => pg_list_cell(pg_folder_list_name($file['folder_name']), 'align-middle text-wrap', $attributes('folder')),
            'description' => pg_list_cell('<div class="text-wrap text-break" style="max-width:20rem;">' . nl2br(h($file['description'])) . '</div>', 'align-middle', $attributes('description')),
            'last_modified' => pg_list_table_when_cell($file['timestamp'], $file['user_username'] ?? ''),
            'actions' => pg_list_cell($output_optimize_button . $output_edit_image_button
                . '<a class="btn btn-sm btn-text theme-secondary" title="' . h(lang('Download')) . '" aria-label="' . h(lang('Download')) . '" download="' . h($file['name']) . '" href="' . OUTPUT_PATH . h($file['name']) . '"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i></a>'),
        ),
    );
}

/**
 * A design file read for the list, or null when it is not one the edit
 * screen opens: a design file outside the bin with a safe plain name.
 *
 * @param int $id
 * @return array|null
 */
function pg_design_file_list_row($id)
{
    $file = db_item(pg_design_file_list_sql($id));

    if (!$file || pg_design_file_name_unsafe((string) $file['name']) || ((string) $file['name'] !== basename((string) $file['name']))) {
        return null;
    }

    return $file;
}

/**
 * The quick edit answer from a file read again: the edited cell and every
 * other cell of the row (a rename changes the type, the thumbnail and the
 * links; Last Modified follows every change). Pure.
 *
 * @param array  $cells pg_design_file_list_cells()['cells']
 * @param string $field the edited cell's column
 * @return array
 */
function pg_design_file_quick_saved(array $cells, $field)
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
 * Writes one value changed on the list through the design file screen's
 * own check and save: the name required, not one the server would run,
 * not ".htaccess" and free (the file is renamed on the disk first); the
 * folder one the screen's picker offers.
 *
 * @param int    $id
 * @param string $field a pg_design_file_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_design_file_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $file = pg_design_file_list_row($id);

    if (!$file) {
        return $refuse(lang('Record not found.'));
    }

    $post = pg_design_file_quick_post($file, $field, $value);
    $stored_name = (string) $file['name'];

    $refusal = pg_design_file_form_refusal($post['name'], $stored_name, (int) $id);

    if ($refusal === '') {
        $refusal = pg_design_file_form_save((int) $id, $stored_name, prepare_file_name($post['name']), $post, (int) $user['id']);
    }

    if ($refusal !== '') {
        return $refuse($refusal);
    }

    $row = pg_design_file_list_row($id);

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    $built = pg_design_file_list_cells($row, $quick_edit, false, PATH . SOFTWARE_DIRECTORY . '/view_design_files.php');

    return pg_design_file_quick_saved($built['cells'], $field);
}
