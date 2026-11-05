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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/arrival_date_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/arrival_date_list_edit.php');

$quick_edit = pg_arrival_date_quick_edit_columns();

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn,
// with the arrival date screen's rights (validate_ecommerce_access()) and
// checks (pg_arrival_date_quick_update()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_arrival_date_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

// Every arrival date is printed: the list table driver sorts and searches
// them in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();
$user = validate_user();
validate_ecommerce_access($user);

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'arrival_date'  => lang('Arrival Date'),
    'status'        => lang('Status'),
    'start_date'    => lang('Start Date'),
    'end_date'      => lang('End Date'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_arrival_dates', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'arrival_dates.name',
    'arrival_date'  => 'arrival_dates.arrival_date',
    'status'        => 'arrival_dates.status',
    'start_date'    => 'arrival_dates.start_date',
    'end_date'      => 'arrival_dates.end_date',
    'last_modified' => 'arrival_dates.timestamp',
));

// Get the current date so that later we can figure out if arrival dates are active.
$current_date = date('Y-m-d');

foreach ((array) db_items(pg_arrival_date_list_sql() . " ORDER BY " . $order_by) as $row) {
    $list_rows[] = array('id' => (int) $row['id'], 'cells' => pg_arrival_date_list_cells($row, $quick_edit, $current_date));
}

$list = pg_list_table_printed(array(
    'list' => 'view_arrival_dates',
    'columns' => pg_arrival_date_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_arrival_dates.php', 'columns' => $quick_edit),
));

print 
pg_page_shell([
        'title'=> lang('All Shipping Arrival Dates'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Shipping Arrival Dates'),
        'heading_description' => lang('All arrival dates for determining shipping methods for all deliveries.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_arrival_date.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_arrival_dates'), pg_list_sort_menu('view_arrival_dates.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Shipping Arrival Dates')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();
