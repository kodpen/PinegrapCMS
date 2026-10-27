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
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_recurring_commission_profiles');

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

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'affiliate_name' => lang('Affiliate Name'),
    'affiliate_code' => lang('Affiliate Code'),
    'enabled'        => lang('Enabled'),
    'amount'         => lang('Amount'),
    'start_date'     => lang('Start Date'),
    'frequency'      => lang('Frequency'),
    'commissions'    => lang('Commissions'),
    'order_number'   => lang('Order'),
    'product'        => lang('Product'),
    'created'        => lang('Created'),
    'last_modified'  => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_recurring_commission_profiles', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'affiliate_name' => 'contacts.affiliate_name',
    'affiliate_code' => 'recurring_commission_profiles.affiliate_code',
    'enabled'        => 'recurring_commission_profiles.enabled',
    'amount'         => 'recurring_commission_profiles.amount',
    'start_date'     => 'recurring_commission_profiles.start_date',
    'frequency'      => 'recurring_commission_profiles.period',
    'commissions'    => 'recurring_commission_profiles.number_of_commissions',
    'order_number'   => 'orders.order_number',
    'product'        => 'recurring_commission_profiles.product_name',
    'created'        => 'recurring_commission_profiles.created_timestamp',
    'last_modified'  => 'recurring_commission_profiles.last_modified_timestamp',
));

// pg_list_state() hands back the page as a positive whole number (1 when the
// address has none or a bogus one), so it can go straight into LIMIT below.
$screen = $list_state['screen'];

$output_screen_links = '';

if ($number_of_screens > 1) {
    $output_screen_links .= '
            <nav class="mt-5 navigation " aria-label="data pagination"> 
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
            $active = 'active';
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
    ORDER BY $order_by
    LIMIT " . (($screen - 1) * $max) . ", " . $max;
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
        $recurring_commission_profile['number_of_commissions'] = pg_format_number($recurring_commission_profile['number_of_commissions'], 0);
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
                <button type="button" class="btn-data-control m-1 btn btn-outline theme-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'edit_recurring_commission_profile.php?id=' . $recurring_commission_profile['id'] . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="btn-data-control m-1 btn btn-outline theme-danger border-2" title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="align-middle chart_label">' . h($recurring_commission_profile['affiliate_name']) . '</td>
            <td class="align-middle">' . h($recurring_commission_profile['affiliate_code']) . '</td>
            <td class="align-middle text-center">' . $output_enabled_check_mark . '</td>
            <td class="align-middle text-end"><span class="badge theme-primary fg-white fw-light">' . pg_format_money($recurring_commission_profile['amount'] / 100, BASE_CURRENCY_SYMBOL) . '</td>
            <td class="align-middle">' . prepare_form_data_for_output($recurring_commission_profile['start_date'], 'date') . '</td>
            <td class="align-middle">' . lang(ucwords($recurring_commission_profile['period'])) . '</td>
            <td class="align-middle"><span class="badge theme-success fg-white fw-light">' . $recurring_commission_profile['number_of_commissions'] . '</span></td>
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
        'heading'=>lang('All Recurring Commission Profiles'),
        'heading_description' => lang('View all profiles for recurring commissions.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            <div class="row mb-3  flex-wrap">
                <div class="col-12 text-center md:text-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_recurring_commission_profiles.php', $sort_columns, $sort_levels, $sort_default) . '
                    </nav>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Affiliate Name'), $sort_levels, '', '', 'affiliate_name') . '</th>
                                <th>' . get_column_heading(lang('Affiliate Code'), $sort_levels, '', '', 'affiliate_code') . '</th>
                                <th class="text-center">' . get_column_heading(lang('Enabled'), $sort_levels, '', '', 'enabled') . '</th>
                                <th class="text-end">' . get_column_heading(lang('Amount'), $sort_levels, '', '', 'amount') . '</th>
                                <th>' . get_column_heading(lang('Start Date'), $sort_levels, '', '', 'start_date') . '</th>
                                <th>' . get_column_heading(lang('Frequency'), $sort_levels, '', '', 'frequency') . '</th>
                                <th>' . get_column_heading(lang('Commissions'), $sort_levels, '', '', 'commissions') . '</th>
                                <th>' . get_column_heading(lang('Order'), $sort_levels, '', '', 'order_number') . '</th>
                                <th>' . get_column_heading(lang('Product'), $sort_levels, '', '', 'product') . '</th>
                                <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
            ' . $output_screen_links . '
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
?>