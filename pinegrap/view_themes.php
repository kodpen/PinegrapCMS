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

$liveform = new liveform('view_themes');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'              => lang('File Name'),
    'description'       => lang('Description'),
    'folder'            => lang('Folder'),
    'activated_desktop' => lang('Activated for Desktop'),
    'activated_mobile'  => lang('Activated for Mobile'),
    'last_modified'     => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_themes', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'              => 'name',
    'description'       => 'description',
    'folder'            => 'folder_name',
    'activated_desktop' => 'activated_desktop_theme',
    'activated_mobile'  => 'activated_mobile_theme',
    'last_modified'     => 'timestamp',
));

// get all stylesheet files
$query =
    "SELECT
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
        (type = 'css')
        AND (design = '1')
        AND (theme = '1')
    ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$files = array();
while ($row = mysqli_fetch_assoc($result)) {
    $files[] = $row;
}

// Every theme is printed: the list table driver sorts and searches them in
// the browser (browser mode) and keeps a heading's sort in the session.
$list_rows = array();

foreach ($files as $file) {
    // The folder's access control, shown beside its name.
    $folder_access_control_type = get_access_control_type($file['folder']);
    $output_access_control = get_access_control_type_name($folder_access_control_type);

    $list_rows[] = array(
        'id' => $file['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_theme_file.php?id=' . (int) $file['id'] . '" data-pg-open>' . h($file['name']) . '</a>'),
            'description' => pg_list_cell(nl2br(h($file['description']))),
            'folder' => pg_list_cell(pg_folder_list_name($file['folder_name']) . '<span class="badge fw-light fg-reset d-inline">(' . $output_access_control . ')</span>', 'align-middle text-wrap ' . $folder_access_control_type),
            'activated_desktop' => pg_list_table_check_cell($file['activated_desktop_theme'] == 1),
            'activated_mobile' => pg_list_table_check_cell($file['activated_mobile_theme'] == 1),
            'last_modified' => pg_list_table_when_cell($file['timestamp'], $file['user_username'] ?? ''),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_themes',
    'columns' => array(
        'name' => array('heading' => lang('File Name')),
        'description' => array('heading' => lang('Description')),
        'folder' => array('heading' => lang('Folder')),
        'activated_desktop' => array('heading' => lang('Activated for Desktop'), 'kind' => 'check'),
        'activated_mobile' => array('heading' => lang('Activated for Mobile'), 'kind' => 'check'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

print
pg_page_shell([
        'title'=> lang('All Themes'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('All Themes'),
        'heading_description' => lang('All CSS stylesheet files used to add consistency to design and content.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_theme_file.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_themes'), pg_list_sort_menu('view_themes.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Themes')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
output_footer();

$liveform->remove_form('view_themes');