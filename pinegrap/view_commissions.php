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
validate_ecommerce_access($user);



include_once('liveform.class.php');
$liveform = new liveform('view_commissions');

// NOTE: $sql_status is never assigned anywhere in this script, so the status filter it was
// meant to carry is never applied.  Starting it empty keeps the current behaviour and stops
// the warning; the filter itself still needs to be implemented.
$sql_status = '';

// if advanced filters are not set yet, then default them to off
if (isset($_SESSION['software']['ecommerce']['view_commissions']['advanced_filters']) == false) {
    $_SESSION['software']['ecommerce']['view_commissions']['advanced_filters'] = false;
}

// if necessary create commission instances from recurring profiles
update_recurring_commissions();

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ecommerce']['view_commissions'][$key] = trim($value);
    }
}
// if advanced filters value was passed in the query string
if (isset($_REQUEST['advanced_filters']) == true) {
    // if advanced filters should be turned on
    if ($_REQUEST['advanced_filters'] == 'true') {
        $_SESSION['software']['ecommerce']['view_commissions']['advanced_filters'] = true;

    // else advanced filters should be turned off
    } else {
        $_SESSION['software']['ecommerce']['view_commissions']['advanced_filters'] = false;
    }
}

// if the form has not been submitted yet, then set default values for fields
if (isset($_SESSION['software']['ecommerce']['view_commissions']['start_month']) == FALSE) {
    // set the start date to a month ago
    $_SESSION['software']['ecommerce']['view_commissions']['start_month'] = date('m', time() - 2678400);
    $_SESSION['software']['ecommerce']['view_commissions']['start_day'] = date('d', time() - 2678400);
    $_SESSION['software']['ecommerce']['view_commissions']['start_year'] = date('Y', time() - 2678400);
    
    // set the stop date to today
    $_SESSION['software']['ecommerce']['view_commissions']['stop_month'] = date('m');
    $_SESSION['software']['ecommerce']['view_commissions']['stop_day'] = date('d');
    $_SESSION['software']['ecommerce']['view_commissions']['stop_year'] = date('Y');
    
    // set status to [All]
    $_SESSION['software']['ecommerce']['view_commissions']['status'] = '[' . lang('All') . ']';
}


// get timestamps for start and stop dates
$start_timestamp = mktime(0, 0, 0, ($_SESSION['software']['ecommerce']['view_commissions']['start_month'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['start_day'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['start_year'] ?? ''));
$stop_timestamp = mktime(23, 59, 59, ($_SESSION['software']['ecommerce']['view_commissions']['stop_month'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['stop_day'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['stop_year'] ?? ''));

// get oldest timestamp
$query = "SELECT MIN(created_timestamp) FROM commissions";
$result = mysqli_query(db::$con, $query) or output_error("Query failed.");
$row = mysqli_fetch_row($result);
$oldest_timestamp = $row[0];

// get minimum year from oldest timestamp
$oldest_year = date('Y', $oldest_timestamp);
$current_year = date('Y');

$years = array();

// create html for year options
for ($i = $oldest_year; $i <= $current_year; $i++) {
    $years[] = $i;
}

// prepare statuses for pick list
$statuses =
    array(
        'pending',
        'payable',
        'ineligible',
        'paid'
    );

$output_status_options = '';

// loop through the statuses in order to prepare pick list options
foreach ($statuses as $status) {
    $selected = '';
    
    // if this is the selected status, then select it
    if ($status == ($_SESSION['software']['ecommerce']['view_commissions']['status'] ?? '')) {
        $selected = ' selected="selected"';
    }
    
    $output_status_options .= '<option value="' . $status . '"' . $selected . '>' . lang(ucwords($status)) . '</option>';
}


// get total number of results for all screens
$query =
    "SELECT COUNT(commissions.id) as number_of_results
    FROM commissions
    LEFT JOIN contacts ON commissions.affiliate_code = contacts.affiliate_code
    LEFT JOIN orders ON commissions.order_id = orders.id
    WHERE
        (commissions.created_timestamp >= '" . $start_timestamp . "')
        AND (commissions.created_timestamp <= '" . $stop_timestamp . "')
        " . $sql_status . " ";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$number_of_results = $row['number_of_results'];

// get total number of commissions
$query = "SELECT COUNT(id) as all_commissions FROM commissions";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$all_commissions = $row['all_commissions'];

// define the maximum number of results
$max = 100;

// get number of screens
$number_of_screens = ceil($number_of_results / $max);

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

if ($number_of_screens > 1) {
    $output_screen_links .= '
            <nav class="mt-3 navigation " aria-label="data pagination"> 
                <ul class="pagination pagination-sm flex-wrap justify-content-center">';
    // build Previous button if necessary
    $previous = $screen - 1;
    // if previous screen is greater than zero, output previous link
    if ($previous > 0) {
        $output_screen_links .= '<li class="page-item mt-1 mb-1"><a class="page-link" href="view_commissions.php?screen=' . $previous . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    }else{
        $output_screen_links .= '<li class="page-item mt-1 mb-1 disabled"><a class="page-link" href="#!" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    }

    // build HTML output for links to screens
    for ($i = 1; $i <= $number_of_screens; $i++) { 
        // if this number is the current screen, then select option
        if ($i == $screen) {
            $active = 'active"';
        // else this number is not the current screen, so do not select option
        } else {
            $active = '';
        }
        $output_screen_links .= '<li class="page-item mt-1 mb-1 ' . $active . '"><a class="page-link " href="view_commissions.php?screen=' . $i . '">' . $i . '</a></li>';
    }
    // build Next button if necessary
    $next = $screen + 1;
    // if next screen is less than or equal to the total number of screens, output next link
    if ($next <= $number_of_screens) {
        $output_screen_links .= '<li class="page-item mt-1 mb-1"><a class="page-link" href="view_commissions.php?screen=' . $next . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    }else{
        $output_screen_links .= '<li class="page-item mt-1 mb-1 disabled"><a class="page-link" href="#!" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    }
    $output_screen_links .= '</ul></nav>';
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_commissions']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_commissions']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_commissions']['sort'] ?? '')) {
    case lang('Affiliate Name'):
        $sort_column = 'contacts.affiliate_name';
        break;
        
    case lang('Affiliate Code'):
        $sort_column = 'commissions.affiliate_code';
        break;
        
    case lang('Reference Code'):
        $sort_column = 'commissions.reference_code';
        break;
        
    case lang('Status'):
        $sort_column = 'commissions.status';
        break;
    
    case lang('Amount'):
        $sort_column = 'commissions.amount';
        break;
        
    case lang('Frequency'):
        $sort_column = 'recurring_commission_profiles.period';
        break;
        
    case lang('Product'):
        $sort_column = 'recurring_commission_profiles.product_name';
        break;

    case lang('Created'):
        $sort_column = 'commissions.created_timestamp';
        break;
        
    case lang('Last Modified'):
        $sort_column = 'commissions.last_modified_timestamp';
        break;

    default:
        $sort_column = 'commissions.last_modified_timestamp';
        $_SESSION['software']['ecommerce']['view_commissions']['sort'] = lang('Last Modified');
        $_SESSION['software']['ecommerce']['view_commissions']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ecommerce']['view_commissions']['order']) == FALSE) {
    $_SESSION['software']['ecommerce']['view_commissions']['order'] = 'asc';
}


// get results for just this screen
$query =
    "SELECT
        commissions.id,
        commissions.affiliate_code,
        commissions.reference_code,
        commissions.status,
        commissions.amount,
        commissions.created_timestamp,
        commissions.last_modified_timestamp,
        contacts.affiliate_name,
        orders.order_number,
        recurring_commission_profiles.period,
        recurring_commission_profiles.product_name,
        recurring_commission_profiles.product_short_description,
        created_user.user_username as created_username,
        last_modified_user.user_username as last_modified_username
    FROM commissions
    LEFT JOIN contacts ON commissions.affiliate_code = contacts.affiliate_code
    LEFT JOIN orders ON commissions.order_id = orders.id
    LEFT JOIN recurring_commission_profiles ON commissions.recurring_commission_profile_id = recurring_commission_profiles.id
    LEFT JOIN user as created_user ON commissions.created_user_id = created_user.user_id
    LEFT JOIN user as last_modified_user ON commissions.last_modified_user_id = last_modified_user.user_id
    WHERE
        (commissions.created_timestamp >= '" . $start_timestamp . "')
        AND (commissions.created_timestamp <= '" . $stop_timestamp . "')
        " . $sql_status . "
    ORDER BY " . $sort_column . " " . escape(($_SESSION['software']['ecommerce']['view_commissions']['order'] ?? '')) . "";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$commissions = array();

// loop through results in order to add them to array
while ($row = mysqli_fetch_assoc($result)) {
    $commissions[] = $row;
}


$output_rows = '';

// loop through all commissions in order to output them
foreach ($commissions as $commission) {
    // if the frequency is blank, then this is not a recurring commission, so set frequency to "one-time"
    if ($commission['period'] == '') {
        $commission['period'] = 'One-Time';
    }
    
    // if there is a short description for the product, then prepend " - " so it appears correctly after the product name
    if ($commission['product_short_description'] != '') {
        $commission['product_short_description'] = ' - ' . $commission['product_short_description'];
    }

    //default
    $output_status_classes = 'bg-secondary text-dark fw-light';

    if($commission['status'] == 'pending'){
        $output_status_classes = 'bg-warning text-dark fw-light';
    } else if($commission['status'] == 'payable'){
        $output_status_classes = 'bg-primary text-light fw-light';
    } else if($commission['status'] == 'ineligible'){
        $output_status_classes = 'bg-danger text-light fw-light';
    } else if($commission['status'] == 'paid'){
        $output_status_classes = 'bg-success text-light fw-light';
    }

    $created_username = '';
            
    if ($commission['created_username']) {
        $created_username = lang(array('string'=>'by {var:1}','vars'=>array( h($commission['created_username']) ) ) );
    }
    
    $last_modified_username = '';
    
    if ($commission['last_modified_username']) {
        $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($commission['last_modified_username']) ) ) );
    }
    
    $output_rows .=
        '<tr>
            <td class="align-middle text-start col-reorder-none">
                <button type="button" class="btn-data-control m-1 btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_commission.php?id=' . $commission['id'] . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="btn-data-control m-1 btn btn-outline-danger border-2" data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="align-middle chart_label">' . h($commission['affiliate_name']) . '</td>
            <td class="align-middle">' . h($commission['affiliate_code']) . '</td>
            <td class="align-middle">' . $commission['reference_code'] . '</td>
            <td class="align-middle"><span class="badge ' . $output_status_classes . '">' . lang(ucwords($commission['status'])) . '</span></td>
            <td class="align-middle text-end">' . BASE_CURRENCY_SYMBOL . number_format($commission['amount'] / 100, 2, '.', ',') . '</td>
            <td class="align-middle">' . lang(ucwords($commission['period'])) . '</td>
            <td class="align-middle">' . $commission['order_number'] . '</td>
            <td class="align-middle">' . h($commission['product_name']) . h($commission['product_short_description']) . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $commission['created_timestamp'])) . '  ' . $created_username . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $commission['last_modified_timestamp'])) . '  ' . $last_modified_username . '</td>
        </tr>';
}

// get pending total
$query =
    "SELECT SUM(commissions.amount) as pending_total
    FROM commissions
    LEFT JOIN contacts ON commissions.affiliate_code = contacts.affiliate_code
    LEFT JOIN orders ON commissions.order_id = orders.id
    WHERE
        (commissions.status = 'pending')
        AND (commissions.created_timestamp >= '" . $start_timestamp . "')
        AND (commissions.created_timestamp <= '" . $stop_timestamp . "')";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$pending_total = $row['pending_total'];

// get payable total
$query =
    "SELECT SUM(commissions.amount) as payable_total
    FROM commissions
    LEFT JOIN contacts ON commissions.affiliate_code = contacts.affiliate_code
    LEFT JOIN orders ON commissions.order_id = orders.id
    WHERE
        (commissions.status = 'payable')
        AND (commissions.created_timestamp >= '" . $start_timestamp . "')
        AND (commissions.created_timestamp <= '" . $stop_timestamp . "')";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$payable_total = $row['payable_total'];

// get ineligible total
$query =
    "SELECT SUM(commissions.amount) as ineligible_total
    FROM commissions
    LEFT JOIN contacts ON commissions.affiliate_code = contacts.affiliate_code
    LEFT JOIN orders ON commissions.order_id = orders.id
    WHERE
        (commissions.status = 'ineligible')
        AND (commissions.created_timestamp >= '" . $start_timestamp . "')
        AND (commissions.created_timestamp <= '" . $stop_timestamp . "')";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$ineligible_total = $row['ineligible_total'];

// get paid total
$query =
    "SELECT SUM(commissions.amount) as paid_total
    FROM commissions
    LEFT JOIN contacts ON commissions.affiliate_code = contacts.affiliate_code
    LEFT JOIN orders ON commissions.order_id = orders.id
    WHERE
        (commissions.status = 'paid')
        AND (commissions.created_timestamp >= '" . $start_timestamp . "')
        AND (commissions.created_timestamp <= '" . $stop_timestamp . "')";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$paid_total = $row['paid_total'];


// if the advanced filters are off
if (($_SESSION['software']['ecommerce']['view_commissions']['advanced_filters'] ?? '') == false) {
    $output_header_with_options = pg_page_shell([
        'title'=> lang('All Commissions'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Commissions'),
        'auto_main'=>false,
    ]);
    $output_advanced_filters_value = 'true';
    $output_advanced_filters_label = lang('Add Advanced Filters');
    $output_advanced_filters = '';
    $advanced_filters_icon = 'filter_list';
    $output_advanced_filters_class = 'btn-primary';
    $output_advanced_filters = '';

// else the advanced filters are on
} else {
    $output_header_with_options = pg_page_shell(array('extra classes'=>'filters_active', 'auto_main'=>false));
    $output_advanced_filters_value = 'false';
    $output_advanced_filters_label = lang('Remove Advanced Filters');
    $advanced_filters_icon = 'filter_list_off';
    $output_advanced_filters_class = 'btn-danger';

    $output_advanced_filters ='
    <div class="advanced_filters advanced-filter-bar position-fixed-md"  id="advanced_filters" >
        <div class="p-2 m-1 rounded border justify-content-between d-flex flex-wrap header">
            <p class="m-0"><span class="material-icons pe-1">filter_list</span>' . lang('Filters') . '</p>
            <a class="btn btn-close " title="' . $output_advanced_filters_label . '" href="view_commissions.php?advanced_filters=' . $output_advanced_filters_value . '" ></a>
        </div>
        <form class="advanced-filter-body p-2 pt-0 disable_shortcut" id="search_advanced" action="view_commissions.php" method="get" name="form">
            <div class="row">
                <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Date Range') . '</h5></div>
                <div class="col-12">
                    <label class="form-label">' . lang('From') . '</label>
                    <select class="form-select my-1" name="start_month">' . select_month(($_SESSION['software']['ecommerce']['view_commissions']['start_month'] ?? '')) . '</select>
                    <div class="input-group input-group-sm">
                        <select class="form-select my-1" name="start_day">' . select_day(($_SESSION['software']['ecommerce']['view_commissions']['start_day'] ?? '')) . '</select>
                        <select class="form-select my-1" name="start_year">' . select_year($years, ($_SESSION['software']['ecommerce']['view_commissions']['start_year'] ?? '')) . '</select>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">' . lang('To') . '</label>
                    <select class="form-select my-1" name="stop_month">' . select_month(($_SESSION['software']['ecommerce']['view_commissions']['stop_month'] ?? '')) . '</select>
                    <div class="input-group input-group-sm">
                        <select class="form-select my-1" name="stop_day">' . select_day(($_SESSION['software']['ecommerce']['view_commissions']['stop_day'] ?? '')) . '</select>
                        <select class="form-select my-1" name="stop_year">' . select_year($years, ($_SESSION['software']['ecommerce']['view_commissions']['stop_year'] ?? '')) . '</select>
                    </div>
                </div>
                <div class="col-12 text-center position-sticky my-2" style="bottom:.5rem;">
                    <button type="submit" name="submit_data" value="Update" data-loading-content="' . lang('Updating') . '" class="btn btn-primary my-1"><i class="material-icons me-2">sync</i>' . lang('Update') . '</button>
                </div>
            </div>
           
        </form>
    </div>';

}

print
$output_header_with_options . '     
' . $output_advanced_filters . '
<main id="content" class="container">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            <div class="row mb-2  flex-wrap">
                <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('View all order commissions.') . '" title="' . lang('All Commissions') . '">' . lang('All Commissions') . '</h2>
                </div>
                <div class="col-12 col-sm-12 col-md-6 col-xl-3 ">
                    <div class="row justify-content-center justify-content-md-end">
                        <form id="search" action="view_commissions.php" method="get" name="form" class="search_form col-auto">
                            <div class="input-group input-group-sm">
                                <a class="btn btn-sm  my-1 ' . $output_advanced_filters_class . '" data-loading-content=" " title="' . $output_advanced_filters_label . '" href="view_commissions.php?advanced_filters=' . $output_advanced_filters_value . '" ><i class="material-icons">'. $advanced_filters_icon . '</i></a>
                                <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Status') . '" for="filter_select">visibility</label>
                                <select id="status" name="status" class="form-select mt-1 mb-1" title="' . lang('Status') . '" onchange="submit_form(\'search\')"><option value="[' . lang('All') . ']">[' . lang('All') . ']</option>' . $output_status_options . '</select>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang('Action') . '</th> 
                                <th>' . get_column_heading(lang('Affiliate Name'), ($_SESSION['software']['ecommerce']['view_commissions']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Affiliate Code'), ($_SESSION['software']['ecommerce']['view_commissions']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Reference Code'), ($_SESSION['software']['ecommerce']['view_commissions']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Status'), ($_SESSION['software']['ecommerce']['view_commissions']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Amount'), ($_SESSION['software']['ecommerce']['view_commissions']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Frequency'), ($_SESSION['software']['ecommerce']['view_commissions']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Order'), ($_SESSION['software']['ecommerce']['view_commissions']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Product'), ($_SESSION['software']['ecommerce']['view_commissions']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['ecommerce']['view_commissions']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_commissions']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_commissions']['order'] ?? '')) . '</th>
                            </tr>
                        </thead>
                        ' . $output_rows . '
                    </table>
                </div>
            </div>
            <div class="clearfix">
                <div class="col-12 col-sm-6 col-md-4 col-lg-3  float-sm-end">
                    <div class="card my-4 border-primary">
                        <div class="card-body ">
                            <div class="row">
                                <div class="col-12 text-center">
                                    <h5>' . lang('Pending Total') . '</h5>
                                    <p class="fw-bolder text-dark">' . BASE_CURRENCY_SYMBOL . number_format($pending_total / 100, 2, '.', ',') . '</p>
                                    <hr/>
                                </div>
                                <div class="col-12 text-center">
                                    <h5>' . lang('Payable Total') . '</h5>
                                    <p class="fw-bolder text-primary">' . BASE_CURRENCY_SYMBOL . number_format($payable_total / 100, 2, '.', ',') . '</p>
                                    <hr/>
                                </div>
                                <div class="col-12 text-center ">
                                    <h5>' . lang('Ineligible Total') . '</h5>
                                    <p class="fw-bolder text-danger">' . BASE_CURRENCY_SYMBOL . number_format($ineligible_total / 100, 2, '.', ',') . '</p>
                                    <hr/>
                                </div>
                                <div class="col-12 text-center ">
                                    <h5>' . lang('Paid Total') . '</h5>
                                    <p class="fw-bolder text-success">' . BASE_CURRENCY_SYMBOL . number_format($paid_total / 100, 2, '.', ',') . '</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>' .
    output_footer();

$liveform->remove_form();
?>