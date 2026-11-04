<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

$user = validate_user();
validate_area_access($user, 'designer');

$liveform = new liveform('view_design_files');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'type'          => lang('Type'),
    'size'          => lang('Size'),
    'optimized'     => lang('Optimized'),
    'folder'        => lang('Folder'),
    'description'   => lang('Description'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_design_files', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'name',
    // The extension of the name: files.type is cut to four characters.
    'type'          => "LOWER(IF(LOCATE('.', files.name) > 0, SUBSTRING_INDEX(files.name, '.', -1), ''))",
    'size'          => 'size',
    'optimized'     => 'optimized',
    'folder'        => 'folder_name',
    'description'   => 'description',
    'last_modified' => 'timestamp',
));

// get all files
$query =
    "SELECT
        files.id,
        files.name,
        files.folder,
        folder.folder_name,
        folder.folder_archived,
        files.description,
        files.type,
        files.size,
        files.optimized,
        user.user_username,
        files.timestamp,
        files.design
    FROM files
    LEFT JOIN folder ON files.folder = folder.folder_id
    LEFT JOIN user ON files.user = user.user_id
    WHERE (files.design = '1')" . pg_design_files_bin_where() . "
    ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$files = array();
while ($row = mysqli_fetch_assoc($result)) {
    $files[] = $row;
}
// Set once a row is an image: the image editor's configuration is printed
// once after the list, never inside a cell, where the list search would
// match it.
$has_image = false;

// Threshold for the optimization percentage: past it the percentages are not
// computed, since each one decodes and recompresses an image.
$unoptimized_image_types = ['jpg','jpeg','png','gif','bmp','tiff','tif','webp'];
$unoptimized_count = 0;
if ($files) {
    foreach ($files as $f) {
        if (!$f['optimized'] && in_array(pg_file_name_extension($f['name']), $unoptimized_image_types)) {
            $unoptimized_count++;
        }
    }
}
$show_optimize_percent = ($unoptimized_count <= 30);

// Every design file is printed: the list table driver sorts and searches
// them in the browser (browser mode) and keeps a heading's sort in the
// session. Modify Selected (its changes asked in a dialog of the page,
// pg_design_files_modify_dialog()) and Delete Selected post the selected
// ids as files[] (edit_files.php).
$list_rows = array();
$image_types = array('bmp', 'gif', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'webp');

foreach ($files as $file) {
    $file_type = pg_file_name_extension($file['name']);
    $is_image = in_array($file_type, $image_types, true);
    $output_optimize_button = '';
    $output_edit_image_button = '';

    if ($is_image && ($file_type !== 'tif')) {
        if (!$file['optimized'] && (extension_loaded('imagick') || extension_loaded('gd'))) {
            $optimize_percent_html = $show_optimize_percent
                ? '<span class="ps-1 fs-smaller">' . h(lang(array('string' => '{var:1}%', 'vars' => calculate_optimizable_percent(FILE_DIRECTORY_PATH . '/' . $file['name'])))) . '</span>'
                : '';
            $output_optimize_button = '<a class="btn btn-sm btn-text theme-success" title="' . h(lang('Optimize this image')) . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/optimize.php?id=' . h($file['id']) . get_token_query_string_field() . h('&send_to=' . urlencode(PATH . SOFTWARE_DIRECTORY . '/view_design_files.php')) . '"><i class="bi bi-fast-forward-circle" aria-hidden="true"></i><span class="visually-hidden">' . h(lang('Optimize this image')) . '</span>' . $optimize_percent_html . '</a>';
        }

        $output_edit_image_button = '<button type="button" class="btn btn-sm btn-text theme-secondary" title="' . h(lang(array('string'=>'Edit this image with {var:1}','vars'=>array(lang('Image Editor'))))) . '" aria-label="' . h(lang(array('string'=>'Edit this image with {var:1}','vars'=>array(lang('Image Editor'))))) . '" data-pg-image-edit="' . h($file['name']) . '"><i class="bi bi-brush" aria-hidden="true"></i></button>';
        $has_image = true;
    }

    $output_thumbnail = '';

    if ($is_image) {
        // The dimensions in the File Manager's format ("20 × 10 px");
        // nothing for a file that is not a readable image.
        $image_size = @getimagesize(FILE_DIRECTORY_PATH . '/' . $file['name']);
        $output_image_dimensions = ($image_size !== false) ? pg_image_dimensions_label($image_size[0], $image_size[1]) : '';
        $output_thumbnail = '<img style="width: 50px;height:50px;object-fit:cover;" title="' . h($output_image_dimensions) . '" alt="" class="img-fluid img-thumbnail" data-pg-thumb="' . h(pg_image_panel_thumb_url(PATH . encode_url_path($file['name']))) . '" />';
    }

    $list_rows[] = array(
        'id' => $file['id'],
        // A file inside an archived folder is shown in italics.
        'class' => ($file['folder_archived'] == '1') ? 'fst-italic' : '',
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_design_file.php?id=' . (int) $file['id'] . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '" data-pg-open title="' . h($file['name']) . '">' . h($file['name']) . '</a>'),
            'thumbnail' => pg_list_cell($output_thumbnail),
            'type' => pg_list_cell(h($file_type)),
            'size' => pg_list_cell(h(convert_bytes_to_string($file['size'], 'auto')), 'align-middle', array('data-pg-sort-value' => (int) $file['size'])),
            'optimized' => pg_list_table_check_cell($file['optimized']),
            'folder' => pg_list_cell(pg_folder_list_name($file['folder_name']), 'align-middle text-wrap'),
            'description' => pg_list_cell('<div class="text-wrap text-break" style="max-width:20rem;">' . nl2br(h($file['description'])) . '</div>'),
            'last_modified' => pg_list_table_when_cell($file['timestamp'], $file['user_username'] ?? ''),
            'actions' => pg_list_cell($output_optimize_button . $output_edit_image_button
                . '<a class="btn btn-sm btn-text theme-secondary" title="' . h(lang('Download')) . '" aria-label="' . h(lang('Download')) . '" download="' . h($file['name']) . '" href="' . OUTPUT_PATH . h($file['name']) . '"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i></a>'),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_design_files',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'thumbnail' => array('heading' => lang('Thumbnail'), 'kind' => 'image'),
        'type' => array('heading' => lang('Type')),
        'size' => array('heading' => lang('Size'), 'kind' => 'number'),
        'optimized' => array('heading' => lang('Optimized'), 'kind' => 'check'),
        'folder' => array('heading' => lang('Folder')),
        'description' => array('heading' => lang('Description')),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
        'actions' => array('heading' => lang('Action'), 'kind' => 'actions'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'ids_field' => 'files[]',
));

$output_bulk_buttons =
    '<button type="button" value="Modify Selected" class="no-submit btn btn-sm btn-solid theme-primary" data-pg-modify-open><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . lang('Modify Selected') . '</button>'
    . '<button type="button" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="' . h(lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('files'))))) . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang('Delete Selected') . '</button>';

print
pg_page_shell(
    array(
        'title'=> lang('All Design Files'),
        'extra classes'=>'designer',
        'icon'=>'design', 
        'heading'=> lang('All Design Files'),
        'heading_description' => lang('All design files that can be referenced by any theme, page style, or region, and managed only by site designers.'),
        'cancel'=> false
    )
)  . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'primary' => array('label' => lang('Upload File'), 'url' => 'add_design_file.php?send_to=' . urlencode(REQUEST_URL), 'icon' => 'bi-upload'),
        'grow'    => '<span class="small fg-secondary">' . lang('Disk Usage') . ': ' . h(convert_bytes_to_string(pg_files_disk_usage(), 2)) . '</span>',
        'search'  => pg_list_table_search_box('view_design_files'),
        'view'    => pg_list_sort_menu('view_design_files.php', $sort_columns, $sort_levels, $sort_default),
    )) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <form name="form" id="view_design_files_form" action="edit_files.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="action" />
                <input type="hidden" name="from" value="view_design_files" />
                <input type="hidden" name="send_to" value="' . h(get_request_uri()) . '" />
                ' . pg_list_table_html($list['source'], $list['page'], lang('All Design Files'), $output_bulk_buttons) . '
            </form>
        </div>
    </div>
    ' . ($has_image ? pg_image_editor_includes() : '') . '
</main>
' . pg_design_files_modify_dialog($user, 'view_design_files_form') . '
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
output_footer();

$liveform->remove_form('view_design_files');