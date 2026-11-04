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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/menu_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/menu_list_edit.php');

$quick_edit = pg_menu_quick_edit_columns();

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn.
// The rights are the menu screen's (validate_area_access() 'designer').
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return (int) $user['role'] <= 1;
    });

    $saved = pg_menu_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $quick_edit, REQUEST_URL);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_area_access($user, 'designer');

$liveform = new liveform('view_menus');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'effect'        => lang('Effect'),
    'first_level_popup_position'  => lang('First Expand Menu'),
    'second_level_popup_position' => lang('Then Expand Menu'),
    'class'         => lang('Classes'),
    'active_item_class' => lang('Active Item Classes'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_menus', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'menus.name',
    'effect'        => 'menus.effect',
    'first_level_popup_position'  => 'menus.first_level_popup_position',
    'second_level_popup_position' => 'menus.second_level_popup_position',
    'class'         => 'menus.class',
    'active_item_class' => 'menus.active_item_class',
    'created'       => 'menus.created_timestamp',
    'last_modified' => 'menus.last_modified_timestamp',
));

// Every menu is printed: the list table driver sorts and searches them in
// the browser (browser mode) and keeps a heading's sort in the session.
$list_rows = array();

foreach ((array) db_items(pg_menu_list_sql() . " ORDER BY " . $order_by) as $menu) {
    $list_rows[] = array('id' => $menu['id'], 'cells' => pg_menu_list_cells($menu, $quick_edit, REQUEST_URL));
}

$list = pg_list_table_printed(array(
    'list' => 'view_menus',
    'columns' => pg_menu_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_menus.php', 'columns' => $quick_edit),
));

echo
pg_page_shell([
        'title'=> lang('All Menus'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('All Menus'),
        'heading_description' => lang('All shared menus that can be added to any page style and managed by any site manager.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_menu.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_menus'), pg_list_sort_menu('view_menus.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Menus')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
output_footer();

$liveform->remove_form();