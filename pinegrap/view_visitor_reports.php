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
validate_visitors_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_visitor_reports');

$output_web_statistics_link = '';

// if an external web stats link is supplied in the settings, then prepare link to web stats
if ((defined('STATS_URL') == true) && (STATS_URL != '')) {
	$output_web_statistics_link = '<a class="btn btn-sm btn-text theme-secondary" href="' . h(STATS_URL) . '" target="_blank"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . lang('Website Analytics') . '</a>';
}


// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_visitor_reports', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'visitor_reports.name',
    'created'       => 'visitor_reports.created_timestamp',
    'last_modified' => 'visitor_reports.last_modified_timestamp',
));

// get all visitor reports
$query =
    "SELECT
        visitor_reports.id,
        visitor_reports.name,
        created_user.user_username as created_username,
        visitor_reports.created_timestamp,
        last_modified_user.user_username as last_modified_username,
        visitor_reports.last_modified_timestamp
    FROM visitor_reports
    LEFT JOIN user AS created_user ON visitor_reports.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON visitor_reports.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// Every saved report is printed: the list table driver sorts and searches
// them in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

while ($row = mysqli_fetch_assoc($result)) {
    $list_rows[] = array(
        'id' => (int) $row['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="view_visitor_report.php?id=' . (int) $row['id'] . '" data-pg-open>' . h($row['name']) . '</a>'),
            'created' => pg_list_table_when_cell($row['created_timestamp'], (string) $row['created_username']),
            'last_modified' => pg_list_table_when_cell($row['last_modified_timestamp'], (string) $row['last_modified_username']),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_visitor_reports',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

print

    pg_page_shell(
        array(
            'title'=> lang('Saved visitor reports'),
            'extra classes'=>'visitor',
            'icon'=>'visitor', 
            'heading'=> lang('Saved visitor reports'),
            'heading_description' => lang('Create real-time reports on all website visitor activities.'),
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
                'primary'   => array('label' => lang('Create'), 'url' => 'view_visitor_report.php', 'icon' => 'bi-plus-circle'),
                'secondary' => array(array('label' => lang('Overview'), 'url' => 'visitors.php', 'icon' => 'bi-graph-up'), $output_web_statistics_link),
                'search'    => array(pg_list_table_search_box('view_visitor_reports'), pg_list_sort_menu('view_visitor_reports.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('Saved visitor reports')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();