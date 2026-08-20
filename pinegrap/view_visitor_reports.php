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
validate_visitors_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_visitor_reports');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['stats']['view_visitor_reports'][$key] = trim($value);
    }
}

$output_web_statistics_link = '';

// if an external web stats link is supplied in the settings, then prepare link to web stats
if ((defined('STATS_URL') == true) && (STATS_URL != '')) {
	$output_web_statistics_link = '<a class="btn btn-sm btn-secondary m-1 " href="' . STATS_URL . '" target="_blank"><span class="bi bi-box-arrow-up-right me-2"></span>' . lang(array('string'=>'Website Analytics') ) . '</a>';
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
if (isset($_SESSION['software']['stats']['view_visitor_reports']['sort']) == false) {
    $_SESSION['software']['stats']['view_visitor_reports']['sort'] = '';
}

switch (($_SESSION['software']['stats']['view_visitor_reports']['sort'] ?? '')) {
    case 'Name':
        $sort_column = 'visitor_reports.name';
        break;

    case 'Created':
        $sort_column = 'visitor_reports.created_timestamp';
        break;

    case 'Last Modified':
        $sort_column = 'visitor_reports.last_modified_timestamp';
        break;

    default:
        $sort_column = 'visitor_reports.last_modified_timestamp';
        $_SESSION['software']['stats']['view_visitor_reports']['sort'] = 'Last Modified';
        $_SESSION['software']['stats']['view_visitor_reports']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['stats']['view_visitor_reports']['order']) == false) {
    $_SESSION['software']['stats']['view_visitor_reports']['order'] = 'asc';
}

$my_visitor_reports = 0;
$all_visitor_reports = 0;

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
    ORDER BY $sort_column " . escape(($_SESSION['software']['stats']['view_visitor_reports']['order'] ?? ''));

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$visitor_reports = array();

while ($row = mysqli_fetch_assoc($result)) {
    $visitor_reports[] = $row;
    // Add one to all visitor reports
    $all_visitor_reports++;
    // Add one to my visitor reports
    $my_visitor_reports++;
}

$output_rows = '';

// if there is at least one result to display
if ($visitor_reports) {
    foreach ($visitor_reports as $visitor_report) {

        $created_username = '';
            
        if ($visitor_report['created_username']) {
            $created_username = lang(array('string'=>'by {var:1}','vars'=>array( h($visitor_report['created_username']) ) ) );
        }
        
        $last_modified_username = '';
        
        if ($visitor_report['last_modified_username']) {
            $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($visitor_report['last_modified_username']) ) ) );
        }

        $output_link_url  = 'view_visitor_report.php?id=' . $visitor_report['id'];
        $output_rows .=
            '<tr>
                <td class="align-middle text-start">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('View') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-eye"></i></button>
                </td>
                <td class="chart_label">' . h($visitor_report['name']) . '</td>
                <td>' . get_relative_time(array('timestamp' => $visitor_report['created_timestamp'])) . ' ' . $created_username . '</td>
                <td>' . get_relative_time(array('timestamp' => $visitor_report['last_modified_timestamp'])) . ' ' . $last_modified_username . '</td>
            </tr>';
    }
}

print

    pg_page_shell(
        array(
            'title'=> lang('All Visitor Reports'),
            'extra classes'=>'visitor',
            'icon'=>'visitor', 
            'heading'=> lang('All Visitor Reports'),
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
                    <h2 class="d-inline-block " data-bs-content="' . lang('Create real-time reports on all website visitor activities.') . '" title="' . lang('All Visitor Reports') . '">' . lang('All Visitor Reports') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="view_visitor_report.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        ' . $output_web_statistics_link . '
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['stats']['view_visitor_reports']['sort'] ?? ''), ($_SESSION['software']['stats']['view_visitor_reports']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['stats']['view_visitor_reports']['sort'] ?? ''), ($_SESSION['software']['stats']['view_visitor_reports']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['stats']['view_visitor_reports']['sort'] ?? ''), ($_SESSION['software']['stats']['view_visitor_reports']['order'] ?? '')) . '</th>
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