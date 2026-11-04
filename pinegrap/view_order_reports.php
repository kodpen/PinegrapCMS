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

validate_ecommerce_report_access();

include_once('liveform.class.php');
$liveform = new liveform('view_order_reports');

$keys_and_values = '';

// The page asked for, a whole number of 1 or more (pg_list_page_number()).
$screen = pg_list_page_number($_REQUEST['screen'] ?? '');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_order_reports', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'order_reports.name',
    'created'       => 'order_reports.created_timestamp',
    'last_modified' => 'order_reports.last_modified_timestamp',
));

$all_order_reports = 0;

// get number of all order reports
$query =
    "SELECT
        COUNT(order_reports.id)
    FROM order_reports";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_order_reports = $row[0];

// get all order reports
$query =
    "SELECT
        order_reports.id,
        order_reports.name,
        created_user.user_username as created_username,
        order_reports.created_timestamp,
        last_modified_user.user_username as last_modified_username,
        order_reports.last_modified_timestamp
    FROM order_reports
    LEFT JOIN user AS created_user ON order_reports.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON order_reports.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$order_reports = array();

while ($row = mysqli_fetch_assoc($result)) {
    $order_reports[] = $row;
}

// Every order report is printed: the list table driver sorts and searches
// them in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

foreach ($order_reports as $order_report) {
    $list_rows[] = array(
        'id' => (int) $order_report['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="view_order_report.php?id=' . (int) $order_report['id'] . '" data-pg-open>' . h($order_report['name']) . '</a>'),
            'created' => pg_list_table_when_cell($order_report['created_timestamp'], (string) $order_report['created_username']),
            'last_modified' => pg_list_table_when_cell($order_report['last_modified_timestamp'], (string) $order_report['last_modified_username']),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_order_reports',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

echo
    pg_page_shell(
        array(
            'title'=> lang('All Order Reports'),
            'extra classes'=>'store',
            'icon'=>'store', 
            'heading'=> lang('All Order Reports'),
            'heading_description' => lang('Create real-time reports on all website order activity.'),
            'cancel'=>false
        )
    ) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'view_order_report.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_order_reports'), pg_list_sort_menu('view_order_reports.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Order Reports')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();
?>