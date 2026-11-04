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

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/theme_file_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/theme_list_edit.php');

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn.
// The rights are the theme screen's (validate_area_access() 'designer').
if (pg_list_edit_requested()) {
    // select_folder() reads the signed-in user; a request without one is
    // refused by pg_list_edit_request() as an expired session.
    $choices = array('folders' => array(), 'classes' => array());

    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN) {
        $user = validate_user();
        $choices = pg_theme_quick_folder_choices();
    }

    $quick_edit = pg_theme_quick_edit_columns($choices['folders'], $choices['classes']);

    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return (int) $user['role'] <= 1;
    });

    $saved = pg_theme_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_area_access($user, 'designer');

// The folders the theme screen's picker offers (select_folder()); a
// Designer or an Administrator sees every one.
$choices = pg_theme_quick_folder_choices();
$quick_edit = pg_theme_quick_edit_columns($choices['folders'], $choices['classes']);

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

// Every theme is printed: the list table driver sorts and searches them in
// the browser (browser mode) and keeps a heading's sort in the session.
$list_rows = array();

foreach ((array) db_items(pg_theme_list_sql() . " ORDER BY " . $order_by) as $theme) {
    // The folder's access control, shown beside its name.
    $theme['access_control_type'] = get_access_control_type($theme['folder']);

    $list_rows[] = array('id' => $theme['id'], 'cells' => pg_theme_list_cells($theme, $quick_edit));
}

$list = pg_list_table_printed(array(
    'list' => 'view_themes',
    'columns' => pg_theme_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_themes.php', 'columns' => $quick_edit),
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