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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

validate_ecommerce_report_access();

include_once('liveform.class.php');
$liveform = new liveform('view_order_reports');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ecommerce']['view_order_reports'][$key] = trim($value);
    }
}

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

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_order_reports']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_order_reports']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_order_reports']['sort'] ?? '')) {
    case lang('Name'):
        $sort_column = 'order_reports.name';
        break;

    case lang('Created'):
        $sort_column = 'order_reports.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'order_reports.last_modified_timestamp';
        break;

    default:
        $sort_column = 'order_reports.last_modified_timestamp';
        $_SESSION['software']['ecommerce']['view_order_reports']['sort'] = lang('Last Modified');
        $_SESSION['software']['ecommerce']['view_order_reports']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ecommerce']['view_order_reports']['order']) == false) {
    $_SESSION['software']['ecommerce']['view_order_reports']['order'] = 'asc';
}

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
    ORDER BY $sort_column " . escape(($_SESSION['software']['ecommerce']['view_order_reports']['order'] ?? ''));

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
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('View') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-eye"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="bi bi-trash"></i></button>-->
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
            'cancel'=>false
        )
    ) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('Create real-time reports on all website order activity.') . '" title="' . lang('All Order Reports') . '">' . lang('All Order Reports') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="view_order_report.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['ecommerce']['view_order_reports']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_order_reports']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['ecommerce']['view_order_reports']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_order_reports']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_order_reports']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_order_reports']['order'] ?? '')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
?>