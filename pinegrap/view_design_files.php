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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/design_file_list_edit.php');

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn.
// The rights are the design file screen's (validate_area_access() 'designer').
if (pg_list_edit_requested()) {
    // select_folder() reads the signed-in user; a request without one is
    // refused by pg_list_edit_request() as an expired session.
    $choices = array('folders' => array(), 'classes' => array());

    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN) {
        $user = validate_user();
        $choices = pg_design_file_quick_folder_choices();
    }

    $quick_edit = pg_design_file_quick_edit_columns($choices['folders'], $choices['classes']);

    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return (int) $user['role'] <= 1;
    });

    $saved = pg_design_file_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_area_access($user, 'designer');

// The folders the design file screen's picker offers (select_folder()); a
// Designer or an Administrator sees every one.
$choices = pg_design_file_quick_folder_choices();
$quick_edit = pg_design_file_quick_edit_columns($choices['folders'], $choices['classes']);

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

$files = (array) db_items(pg_design_file_list_sql() . " ORDER BY " . $order_by);

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

foreach ($files as $file) {
    $built = pg_design_file_list_cells($file, $quick_edit, $show_optimize_percent, REQUEST_URL);
    $has_image = $has_image || $built['image'];

    $list_rows[] = array(
        'id' => $file['id'],
        // A file inside an archived folder is shown in italics.
        'class' => ($file['folder_archived'] == '1') ? 'fst-italic' : '',
        'cells' => $built['cells'],
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_design_files',
    'columns' => pg_design_file_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'ids_field' => 'files[]',
    'edit' => array('url' => 'view_design_files.php', 'columns' => $quick_edit),
));

$output_bulk_buttons =
    '<button type="button" value="Modify Selected" class="no-submit btn btn-sm btn-solid theme-primary" data-pg-modify-open><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . lang('Modify Selected') . '</button>'
    . '<button type="button" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="' . h(lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('files'))))) . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang('Delete Selected') . '</button>';

print
pg_page_shell(
    array(
        'title'=> lang('All Design Files'),
        'extra classes'=>'designer pg-measure-list',
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