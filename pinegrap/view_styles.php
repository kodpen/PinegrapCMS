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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/custom_style_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/style_list_edit.php');

$quick_edit = pg_style_quick_edit_columns();

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn.
// The rights are the custom style screen's (validate_area_access() 'designer').
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return (int) $user['role'] <= 1;
    });

    $saved = pg_style_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_area_access($user, 'designer');

$liveform = new liveform('view_styles');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'theme'         => lang('Theme'),
    'collection'    => lang('Collection'),
    'layout_type'   => lang('Layout Type'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_styles', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'style_name',
    'theme'         => 'theme_name',
    'collection'    => 'collection',
    'layout_type'   => 'layout_type',
    'last_modified' => 'style_timestamp',
));

// Designs made with the visual editor have their own screen
// (view_system_styles.php); this list is the HTML-template styles.
// get the number of folders and pages that use each style
require_once(PG_FUNCTIONS_DIR . '/includes/fn/style_usage.php');
$style_usage = pg_style_usage(
    db_items("SELECT folder_id AS id, folder_parent AS parent_folder_id, folder_style AS style_id, mobile_style_id FROM folder"),
    db_items("SELECT page_folder AS folder_id, page_style AS style_id, mobile_style_id FROM page")
);


// Every style is printed: the list table driver sorts and searches them in
// the browser (browser mode) and keeps a heading's sort in the session.
// Delete Selected posts the selected ids as styles[] (delete_styles.php).
$list_rows = array();

foreach ((array) db_items(pg_style_list_sql() . " ORDER BY " . $order_by) as $style) {
    $folders_using = isset($style_usage[$style['id']]) ? $style_usage[$style['id']]['number_of_folders'] : 0;
    $pages_using = isset($style_usage[$style['id']]) ? $style_usage[$style['id']]['number_of_pages'] : 0;

    $list_rows[] = array('id' => $style['id'], 'cells' => pg_style_list_cells($style, $quick_edit, $folders_using, $pages_using, (int) $user['role'] <= 1));
}

$list = pg_list_table_printed(array(
    'list' => 'view_styles',
    'columns' => pg_style_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'ids_field' => 'styles[]',
    'edit' => array('url' => 'view_styles.php', 'columns' => $quick_edit),
));

// Once the bin holds styles, the ones deleted here go there (delete_styles.php).
require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
$delete_confirmation = pg_recycle_style_ready()
    ? lang('The selected page styles will be moved to the Recycle Bin. Those still used by a folder or page are kept.')
    : lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('page styles'))));

print
pg_page_shell([
        'title'=> lang('All Page Styles'),
        'extra classes'=>'design pg-measure-list',
        'icon'=>'design',
        'heading'=>lang('All Page Styles'),
        'heading_description' => lang('All HTML templates that define the design and content layout for any page.')
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar(array(
                'primary'   => array('label' => lang('Create'), 'url' => 'add_custom_style.php', 'icon' => 'bi-plus-circle'),
                'secondary' => array(
                    array('label' => lang('Page Design Studio'), 'url' => 'view_system_styles.php', 'icon' => 'bi-magic'),
                    // Read-only readiness report of every classic style (legacy_to_designer.php).
                    array('label' => lang('Move to the Page Design Studio'), 'url' => 'legacy_to_designer.php', 'icon' => 'bi-clipboard-check'),
                ),
                'search'    => array(pg_list_table_search_box('view_styles'), pg_list_sort_menu('view_styles.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            <form name="form" action="delete_styles.php" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('All Page Styles'), '<button type="button" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="' . h($delete_confirmation) . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang('Delete Selected') . '</button>') . '
            </form>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
output_footer();

$liveform->remove_form('view_styles');
