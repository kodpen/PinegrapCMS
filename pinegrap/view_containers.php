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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/container_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/container_list_edit.php');

$quick_edit = pg_container_quick_edit_columns();

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn.
// The rights are the container screen's (validate_ecommerce_access()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ((int) $user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_container_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_ecommerce_access($user);

$liveform = new liveform('view_containers');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'enabled'       => lang('Enabled'),
    'length'        => lang('Length'),
    'width'         => lang('Width'),
    'height'        => lang('Height'),
    'weight'        => lang('Weight'),
    'cost'          => lang('Cost'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_containers', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'containers.name',
    'enabled'       => 'containers.enabled',
    'length'        => 'containers.length',
    'width'         => 'containers.width',
    'height'        => 'containers.height',
    'weight'        => 'containers.weight',
    'cost'          => 'containers.cost',
    'created'       => 'containers.created_timestamp',
    'last_modified' => 'containers.last_modified_timestamp',
));

// Every container is printed: the list table driver sorts and searches them
// in the browser (browser mode) and keeps a heading's sort in the session.
$list_rows = array();

foreach ((array) db_items(pg_container_list_sql() . " ORDER BY " . $order_by) as $container) {
    $list_rows[] = array('id' => $container['id'], 'cells' => pg_container_list_cells($container, $quick_edit));
}

$list = pg_list_table_printed(array(
    'list' => 'view_containers',
    'columns' => pg_container_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_containers.php', 'columns' => $quick_edit),
));

echo
    pg_page_shell([
        'title'=> lang('All Containers'),
        'extra classes'=>'products pg-measure-list',
        'icon'=>'store',
        'heading'=>lang('All Containers'),
        'heading_description' => lang('All shipping containers (e.g. boxes) that products are packaged in.'),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                ' . pg_screen_toolbar(array(
                    'primary' => array('label' => lang('Create'), 'url' => 'add_container.php', 'icon' => 'bi-plus-circle'),
                    'search'  => array(pg_list_table_search_box('view_containers'), pg_list_sort_menu('view_containers.php', $sort_columns, $sort_levels, $sort_default)),
                )) . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('All Containers')) . '
            </div>
        </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
    output_footer();

$liveform->remove_form();
