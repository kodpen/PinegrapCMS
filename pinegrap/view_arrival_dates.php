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

$query = "SELECT
            arrival_dates.id as id,
            arrival_dates.name as name,
            arrival_dates.arrival_date as arrival_date,
            arrival_dates.status as status,
            arrival_dates.start_date as start_date,
            arrival_dates.end_date as end_date,
            arrival_dates.custom as custom,
            user.user_username as user,
            arrival_dates.timestamp as timestamp
        FROM arrival_dates
        LEFT JOIN user ON arrival_dates.user = user.user_id
        ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
while ($row = mysqli_fetch_array($result)) {

    $id = $row['id'];
    $name = $row['name'];
    // An unset date (AT-ONCE, or one the customer enters) is not 0/0/0000.
    $arrival_date = pg_arrival_date_form_date_text($row['arrival_date']);
    if (($arrival_date === '') && ($row['custom'] == 1)) {
        $arrival_date = lang('Chosen by the customer');
    }
    $status = $row['status'];
    $start_date = pg_arrival_date_form_date_text($row['start_date']);
    $end_date = pg_arrival_date_form_date_text($row['end_date']);
    $username = $row['user'];
    $timestamp = $row['timestamp'];
    
    $output_link_url = 'edit_arrival_date.php?id=' . (int) $id;

    // An enabled arrival date outside its dates is not offered: the name
    // says Inactive under it, as the Status badge still reads Enabled.
    if (
        ($row['status'] == 'enabled')
        && ($row['start_date'] <= $current_date)
        && ($row['end_date'] >= $current_date)
    ) {
        $output_status_class = 'status_enabled';
        $output_state = '';
    } else {
        $output_status_class = 'status_disabled';
        $output_state = pg_list_status_word(lang('Inactive'));
    }

    $list_rows[] = array(
        'id' => (int) $id,
        'cells' => array(
            'name' => pg_list_cell('<a href="' . h($output_link_url) . '" class="' . $output_status_class . '" data-pg-open>' . h($name) . '</a>' . $output_state),
            'arrival_date' => pg_list_cell(h($arrival_date), 'align-middle text-nowrap', array('data-pg-sort-value' => (int) str_replace('-', '', (string) $row['arrival_date']))),
            'status' => pg_list_cell('<span class="badge fg-white fw-light ' . (($status == 'enabled') ? 'theme-success' : 'theme-danger') . '">' . h(lang(ucwords($status))) . '</span>'),
            'start_date' => pg_list_cell(h($start_date), 'align-middle text-nowrap', array('data-pg-sort-value' => (int) str_replace('-', '', (string) $row['start_date']))),
            'end_date' => pg_list_cell(h($end_date), 'align-middle text-nowrap', array('data-pg-sort-value' => (int) str_replace('-', '', (string) $row['end_date']))),
            'last_modified' => pg_list_table_when_cell($timestamp, (string) $username),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_arrival_dates',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'arrival_date' => array('heading' => lang('Arrival Date'), 'kind' => 'date'),
        'status' => array('heading' => lang('Status'), 'kind' => 'badge'),
        'start_date' => array('heading' => lang('Start Date'), 'kind' => 'date'),
        'end_date' => array('heading' => lang('End Date'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
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
