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

$user = validate_user();

validate_ecommerce_report_access();

include_once('liveform.class.php');
$liveform = new liveform('view_order_reports');

$keys_and_values = '';

// If a screen was passed and it is a positive integer, then use it.
// These checks are necessary in order to avoid SQL errors below for a bogus screen value.
if (
    isset($_REQUEST['screen'])
    and $_REQUEST['screen']
    and is_numeric($_REQUEST['screen'])
    and $_REQUEST['screen'] > 0
    and $_REQUEST['screen'] == round($_REQUEST['screen'])
) {
    $screen = (int) $_REQUEST['screen'];

// Otherwise, use the default, which is the first screen.
} else {
    $screen = 1;
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

$output_rows = '';

// if there is at least one result to display
if ($order_reports) {

    foreach ($order_reports as $order_report) {

        $output_link_url = 'view_order_report.php?id=' . $order_report['id'];

        $created_username = '';
            
        if ($order_report['created_username']) {
            $created_username = lang(array('string'=>'by {var:1}','vars'=>array( h($order_report['created_username']) ) ) );
        }
        
        $last_modified_username = '';
        
        if ($order_report['last_modified_username']) {
            $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($order_report['last_modified_username']) ) ) );
        }
        
        $output_rows .=
            '<tr>
                <td class="align-middle text-start action-buttons">
                    <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('View') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-eye"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline theme-danger border-2 " title="' . lang('Delete') . '" ><i class="bi bi-trash"></i></button>-->
                </td>
                <td class="chart_label align-middle">' . h($order_report['name']) . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $order_report['created_timestamp'])) . ' ' . $created_username . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $order_report['last_modified_timestamp'])) . ' ' . $last_modified_username . '</td>
            </tr>';
    }
}

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
           
            <div class="row mb-3  flex-wrap">
                <div class="col-12 text-center md:text-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="' . h(lang('Button Bar')) . '">
                        <a class="btn btn-sm btn-solid theme-primary m-1 " href="view_order_report.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_order_reports.php', $sort_columns, $sort_levels, $sort_default) . '
                    </nav>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                <th>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
?>