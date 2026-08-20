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
$liveform = new liveform('view_recurring_commission_profiles');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ecommerce']['view_recurring_commission_profiles'][$key] = trim($value);
    }
}

// get total number of results for all screens
$query =
    "SELECT COUNT(recurring_commission_profiles.id) as number_of_results
    FROM recurring_commission_profiles
    LEFT JOIN contacts ON recurring_commission_profiles.affiliate_code = contacts.affiliate_code
    LEFT JOIN orders ON recurring_commission_profiles.order_id = orders.id";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$number_of_results = $row['number_of_results'];

// get total number of commissions
$query = "SELECT COUNT(id) as all_recurring_commission_profiles FROM recurring_commission_profiles";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$all_recurring_commission_profiles = $row['all_recurring_commission_profiles'];

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
        $output_screen_links .= '<li class="page-item mt-1 mb-1"><a class="page-link" href="view_recurring_commission_profiles.php?screen=' . $previous . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
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
        $output_screen_links .= '<li class="page-item mt-1 mb-1 ' . $active . '"><a class="page-link " href="view_recurring_commission_profiles.php?screen=' . $i . '">' . $i . '</a></li>';
    }
    // build Next button if necessary
    $next = $screen + 1;
    // if next screen is less than or equal to the total number of screens, output next link
    if ($next <= $number_of_screens) {
        $output_screen_links .= '<li class="page-item mt-1 mb-1"><a class="page-link" href="view_recurring_commission_profiles.php?screen=' . $next . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    }else{
        $output_screen_links .= '<li class="page-item mt-1 mb-1 disabled"><a class="page-link" href="#!" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    }
    $output_screen_links .= '</ul></nav>';
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? '')) {
    case lang('Affiliate Name'):
        $sort_column = 'contacts.affiliate_name';
        break;
        
    case lang('Affiliate Code'):
        $sort_column = 'recurring_commission_profiles.affiliate_code';
        break;
        
    case lang('Enabled'):
        $sort_column = 'recurring_commission_profiles.enabled';
        break;
    
    case lang('Amount'):
        $sort_column = 'recurring_commission_profiles.amount';
        break;
        
    case lang('Start Date'):
        $sort_column = 'recurring_commission_profiles.start_date';
        break;
        
    case lang('Frequency'):
        $sort_column = 'recurring_commission_profiles.period';
        break;
        
    case lang('Commissions'):
        $sort_column = 'recurring_commission_profiles.number_of_commissions';
        break;
        
    case lang('Product'):
        $sort_column = 'recurring_commission_profiles.product_name';
        break;

    case lang('Created'):
        $sort_column = 'recurring_commission_profiles.created_timestamp';
        break;
        
    case lang('Last Modified'):
        $sort_column = 'recurring_commission_profiles.last_modified_timestamp';
        break;

    default:
        $sort_column = 'recurring_commission_profiles.last_modified_timestamp';
        $_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] = lang('Last Modified');
        $_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order']) == FALSE) {
    $_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] = 'asc';
}

// get results for just this screen
$query =
    "SELECT
        recurring_commission_profiles.id,
        recurring_commission_profiles.affiliate_code,
        recurring_commission_profiles.enabled,
        recurring_commission_profiles.amount,
        recurring_commission_profiles.start_date,
        recurring_commission_profiles.period,
        recurring_commission_profiles.number_of_commissions,
        recurring_commission_profiles.product_name,
        recurring_commission_profiles.product_short_description,
        recurring_commission_profiles.created_timestamp,
        recurring_commission_profiles.last_modified_timestamp,
        contacts.affiliate_name,
        orders.order_number,
        created_user.user_username as created_username,
        last_modified_user.user_username as last_modified_username
    FROM recurring_commission_profiles
    LEFT JOIN contacts ON recurring_commission_profiles.affiliate_code = contacts.affiliate_code
    LEFT JOIN orders ON recurring_commission_profiles.order_id = orders.id
    LEFT JOIN user as created_user ON recurring_commission_profiles.created_user_id = created_user.user_id
    LEFT JOIN user as last_modified_user ON recurring_commission_profiles.last_modified_user_id = last_modified_user.user_id
    ORDER BY " . $sort_column . " " . escape(($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . " ";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$recurring_commission_profiles = array();

// loop through results in order to add them to array
while ($row = mysqli_fetch_assoc($result)) {
    $recurring_commission_profiles[] = $row;
}

$output_rows = '';

// loop through all profiles in order to output them
foreach ($recurring_commission_profiles as $recurring_commission_profile) {
    $output_enabled_check_mark = '';
    
    // if this profile is enabled, then output check mark
    if ($recurring_commission_profile['enabled'] == 1) {
        $output_enabled_check_mark = '<span class="material-icons">task_alt</span>';
    }
    
    // if the number of commissions is 0, then output "Unlimited"
    if ($recurring_commission_profile['number_of_commissions'] == 0) {
        $recurring_commission_profile['number_of_commissions'] = lang('Unlimited');
        
    // else the number of commissions is not 0, so format the number
    } else {
        $recurring_commission_profile['number_of_commissions'] = number_format($recurring_commission_profile['number_of_commissions']);
    }
    
    // if there is a short description for the product, then prepend " - " so it appears correctly after the product name
    if ($recurring_commission_profile['product_short_description'] != '') {
        $recurring_commission_profile['product_short_description'] = ' - ' . $recurring_commission_profile['product_short_description'];
    }
    $created_username = '';
    if ($recurring_commission_profile['created_username']) {
        $created_username = lang(array('string'=>'by {var:1}','vars'=>array( h($recurring_commission_profile['created_username']) ) ) );
    }
    
    $last_modified_username = '';
    
    if ($recurring_commission_profile['last_modified_username']) {
        $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($recurring_commission_profile['last_modified_username']) ) ) );
    }
    
    $output_rows .=
        '<tr>
            <td class="align-middle text-start col-reorder-none">
                <button type="button" class="btn-data-control m-1 btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_recurring_commission_profile.php?id=' . $recurring_commission_profile['id'] . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="btn-data-control m-1 btn btn-outline-danger border-2" data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="align-middle chart_label">' . h($recurring_commission_profile['affiliate_name']) . '</td>
            <td class="align-middle">' . h($recurring_commission_profile['affiliate_code']) . '</td>
            <td class="align-middle text-center">' . $output_enabled_check_mark . '</td>
            <td class="align-middle text-end"><span class="badge bg-primary text-light fw-light">' . BASE_CURRENCY_SYMBOL . number_format($recurring_commission_profile['amount'] / 100, 2, '.', ',') . '</td>
            <td class="align-middle">' . prepare_form_data_for_output($recurring_commission_profile['start_date'], 'date') . '</td>
            <td class="align-middle">' . lang(ucwords($recurring_commission_profile['period'])) . '</td>
            <td class="align-middle"><span class="badge bg-success text-light fw-light">' . $recurring_commission_profile['number_of_commissions'] . '</span></td>
            <td class="align-middle">' . $recurring_commission_profile['order_number'] . '</td>
            <td class="align-middle">' . h($recurring_commission_profile['product_name']) . h($recurring_commission_profile['product_short_description']) . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $recurring_commission_profile['created_timestamp'])) . ' ' . $created_username . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $recurring_commission_profile['last_modified_timestamp'])) .' ' . $last_modified_username . '</td>
        </tr>';
}

print
pg_page_shell([
        'title'=> lang('All Recurring Commission Profiles'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Recurring Commission Profiles')
    ]) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('View all profiles for recurring commissions.') . '" title="' . lang('All Recurring Commission Profiles') . '">' . lang('All Recurring Commission Profiles') . '</h2>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Affiliate Name'), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Affiliate Code'), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . '</th>
                                <th class="text-center">' . get_column_heading(lang('Enabled'), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . '</th>
                                <th class="text-end">' . get_column_heading(lang('Amount'), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Start Date'), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Frequency'), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Commissions'), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Order'), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Product'), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_recurring_commission_profiles']['order'] ?? '')) . '</th>
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