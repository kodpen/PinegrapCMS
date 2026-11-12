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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/ship_date_adjustment_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ship_date_adjustment_list_edit.php');

$quick_edit = pg_ship_date_adjustment_quick_edit_columns(pg_ship_date_adjustment_quick_methods());

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn.
// The rights are the adjustment screen's (validate_ecommerce_access()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ((int) $user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_ship_date_adjustment_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_ship_date_adjustments');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'zip_code_prefix' => lang('Zip Code Prefix'),
    'shipping_method' => lang('Shipping Method'),
    'adjustment'      => lang('Adjustment'),
    'created'         => lang('Created'),
    'last_modified'   => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_ship_date_adjustments', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'zip_code_prefix' => 'ship_date_adjustments.zip_code_prefix',
    'shipping_method' => 'shipping_methods.name',
    'adjustment'      => 'ship_date_adjustments.adjustment',
    'created'         => 'ship_date_adjustments.created_timestamp',
    'last_modified'   => 'ship_date_adjustments.last_modified_timestamp',
));

// Every adjustment is printed: the list table driver sorts and searches
// them in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

foreach ((array) db_items(pg_ship_date_adjustment_list_sql() . " ORDER BY " . $order_by) as $item) {
    $list_rows[] = array('id' => (int) $item['id'], 'cells' => pg_ship_date_adjustment_list_cells($item, $quick_edit));
}

$list = pg_list_table_printed(array(
    'list' => 'view_ship_date_adjustments',
    'columns' => pg_ship_date_adjustment_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_ship_date_adjustments.php', 'columns' => $quick_edit),
));

echo
    pg_page_shell([
        'title'=> lang('All Ship Date Adjustments'),
        'extra classes'=>'products pg-measure-list',
        'icon'=>'store',
        'heading'=>lang('All Ship Date Adjustments'),
        'heading_description' => lang('Allow a ship date for a recipient to be adjusted for a particular zip code prefix and shipping method.'),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                ' . pg_screen_toolbar(array(
                    'primary' => array('label' => lang('Create'), 'url' => 'add_ship_date_adjustment.php', 'icon' => 'bi-plus-circle'),
                    'search'  => array(pg_list_table_search_box('view_ship_date_adjustments'), pg_list_sort_menu('view_ship_date_adjustments.php', $sort_columns, $sort_levels, $sort_default)),
                )) . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('All Ship Date Adjustments')) . '
            </div>
        </div>
    
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
    output_footer();

$liveform->remove_form();
