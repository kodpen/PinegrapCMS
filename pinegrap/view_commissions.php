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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commission_list.php');
$liveform = new liveform('view_commissions');

// if advanced filters are not set yet, then default them to off
if (isset($_SESSION['software']['ecommerce']['view_commissions']['advanced_filters']) == false) {
    $_SESSION['software']['ecommerce']['view_commissions']['advanced_filters'] = false;
}

// if necessary create commission instances from recurring profiles
update_recurring_commissions();

// The date range is kept in the session, so the list opens on the range
// picked last. The sort is kept by pg_list_state().
foreach (array('start_month', 'start_day', 'start_year', 'stop_month', 'stop_day', 'stop_year') as $key) {
    if (isset($_REQUEST[$key]) && is_string($_REQUEST[$key])) {
        $_SESSION['software']['ecommerce']['view_commissions'][$key] = trim($_REQUEST[$key]);
    }
}
// if advanced filters value was passed in the query string
if (isset($_REQUEST['advanced_filters']) == true) {
    $_SESSION['software']['ecommerce']['view_commissions']['advanced_filters'] = ($_REQUEST['advanced_filters'] == 'true');
}

// The range a month ago to today unless one was picked
// (includes/fn/commission_list.php).
$range = pg_commission_list_range();
$start_timestamp = $range['start'];
$stop_timestamp = $range['stop'];

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

// The rows come one page at a time from the commission_list_rows action
// (includes/panel/lists.php), searched and counted on the server.
$list_sort = pg_commission_list_sort();
$sort_columns = $list_sort['sort_columns'];
$sort_default = $list_sort['sort_default'];
$sort_levels = $list_sort['sort_levels'];

// get pending total
$query =
    "SELECT SUM(commissions.amount) as pending_total
    FROM commissions
    LEFT JOIN contacts ON commissions.affiliate_code = contacts.affiliate_code
    LEFT JOIN orders ON commissions.order_id = orders.id
    WHERE
        (commissions.status = 'pending')
        AND (commissions.created_timestamp >= ?)
        AND (commissions.created_timestamp <= ?)";
$result = db_q($query, array((string) $start_timestamp, (string) $stop_timestamp)) or output_error('Query failed.');
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
        AND (commissions.created_timestamp >= ?)
        AND (commissions.created_timestamp <= ?)";
$result = db_q($query, array((string) $start_timestamp, (string) $stop_timestamp)) or output_error('Query failed.');
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
        AND (commissions.created_timestamp >= ?)
        AND (commissions.created_timestamp <= ?)";
$result = db_q($query, array((string) $start_timestamp, (string) $stop_timestamp)) or output_error('Query failed.');
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
        AND (commissions.created_timestamp >= ?)
        AND (commissions.created_timestamp <= ?)";
$result = db_q($query, array((string) $start_timestamp, (string) $stop_timestamp)) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$paid_total = $row['paid_total'];


// if the advanced filters are off
if (($_SESSION['software']['ecommerce']['view_commissions']['advanced_filters'] ?? '') == false) {
    $output_advanced_filters_value = 'true';
    $output_advanced_filters_label = lang('Add Advanced Filters');
    $advanced_filters_icon = 'bi-funnel';
    $output_advanced_filters_class = '';
    $output_advanced_filters = '';

// else the advanced filters are on
} else {
    $output_advanced_filters_value = 'false';
    $output_advanced_filters_label = lang('Remove Advanced Filters');
    $advanced_filters_icon = 'bi-funnel-fill';
    $output_advanced_filters_class = ' active';

    $output_advanced_filters ='
    <div class="advanced_filters advanced-filter-bar position-fixed-md"  id="advanced_filters" >
        <div class="p-3 m-1 rounded-4 border justify-content-between d-flex flex-wrap header">
            <p class="m-0"><span class="material-icons pe-1">filter_list</span>' . lang('Filters') . '</p>
            <a class="btn btn-close " title="' . $output_advanced_filters_label . '" href="view_commissions.php?advanced_filters=' . $output_advanced_filters_value . '" ></a>
        </div>
        <form class="advanced-filter-body p-3 pt-0 disable_shortcut" id="search_advanced" action="view_commissions.php" method="get" name="form">
            <div class="row">
                <div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('Date Range') . '</h5></div>
                <fieldset class="col-12" data-pg-date-selects>
                    <legend class="form-label">' . lang('Start') . '</legend>
                    <select class="form-control my-1" name="start_month" aria-label="' . h(lang('Month')) . '">' . select_month(($_SESSION['software']['ecommerce']['view_commissions']['start_month'] ?? '')) . '</select>
                    <div class="input-group input-group-sm">
                        <select class="form-control my-1" name="start_day" aria-label="' . h(lang('Day')) . '">' . select_day(($_SESSION['software']['ecommerce']['view_commissions']['start_day'] ?? '')) . '</select>
                        <select class="form-control my-1" name="start_year" aria-label="' . h(lang('Year')) . '">' . select_year($years, ($_SESSION['software']['ecommerce']['view_commissions']['start_year'] ?? '')) . '</select>
                    </div>
                </fieldset>
                <fieldset class="col-12" data-pg-date-selects>
                    <legend class="form-label">' . lang('End') . '</legend>
                    <select class="form-control my-1" name="stop_month" aria-label="' . h(lang('Month')) . '">' . select_month(($_SESSION['software']['ecommerce']['view_commissions']['stop_month'] ?? '')) . '</select>
                    <div class="input-group input-group-sm">
                        <select class="form-control my-1" name="stop_day" aria-label="' . h(lang('Day')) . '">' . select_day(($_SESSION['software']['ecommerce']['view_commissions']['stop_day'] ?? '')) . '</select>
                        <select class="form-control my-1" name="stop_year" aria-label="' . h(lang('Year')) . '">' . select_year($years, ($_SESSION['software']['ecommerce']['view_commissions']['stop_year'] ?? '')) . '</select>
                    </div>
                </fieldset>
                ' . get_date_picker_format() . pg_fields_script() . '
                <div class="col-12 text-center position-sticky my-3" style="bottom:.5rem;">
                    <button type="submit" name="submit_data" value="Update" class="btn btn-solid theme-primary my-1"><i class="material-icons me-3">sync</i>' . lang('Update') . '</button>
                </div>
            </div>
           
        </form>
    </div>';

}

print
pg_page_shell([
        'title'=> lang('All Commissions'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Commissions'),
        'heading_description' => lang('View all order commissions.'),
    ]) . '
<main id="content" class="container-fluid">
    ' . $output_advanced_filters . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            <div class="row mb-3  flex-wrap">
                <div class="col-12 text-center md:text-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_commissions.php', $sort_columns, $sort_levels, $sort_default) . '
                        <a class="btn btn-sm btn-text theme-secondary' . $output_advanced_filters_class . '" title="' . $output_advanced_filters_label . '" aria-label="' . $output_advanced_filters_label . '" href="view_commissions.php?advanced_filters=' . $output_advanced_filters_value . '"><i class="bi ' . $advanced_filters_icon . '" aria-hidden="true"></i></a>
                    </nav>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none" data-pg-list-action="commission_list_rows">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang('Action') . '</th> 
                                <th>' . get_column_heading(lang('Affiliate Name'), $sort_levels, '', '', 'affiliate_name') . '</th>
                                <th>' . get_column_heading(lang('Affiliate Code'), $sort_levels, '', '', 'affiliate_code') . '</th>
                                <th>' . get_column_heading(lang('Reference Code'), $sort_levels, '', '', 'reference_code') . '</th>
                                <th>' . get_column_heading(lang('Status'), $sort_levels, '', '', 'status') . '</th>
                                <th>' . get_column_heading(lang('Amount'), $sort_levels, '', '', 'amount') . '</th>
                                <th>' . get_column_heading(lang('Frequency'), $sort_levels, '', '', 'frequency') . '</th>
                                <th>' . get_column_heading(lang('Order'), $sort_levels, '', '', 'order_number') . '</th>
                                <th>' . get_column_heading(lang('Product'), $sort_levels, '', '', 'product') . '</th>
                                <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="d-flow-root">
                <div class="col-12 sm:col-6 md:col-4 lg:col-3  sm:float-end">
                    <div class="card my-7 border-primary">
                        <div class="card-body ">
                            <div class="row">
                                <div class="col-12 text-center">
                                    <h5>' . lang('Pending Total') . '</h5>
                                    <p class="fw-bolder fg-body">' . pg_format_money($pending_total / 100, BASE_CURRENCY_SYMBOL) . '</p>
                                    <hr/>
                                </div>
                                <div class="col-12 text-center">
                                    <h5>' . lang('Payable Total') . '</h5>
                                    <p class="fw-bolder fg-primary">' . pg_format_money($payable_total / 100, BASE_CURRENCY_SYMBOL) . '</p>
                                    <hr/>
                                </div>
                                <div class="col-12 text-center ">
                                    <h5>' . lang('Ineligible Total') . '</h5>
                                    <p class="fw-bolder fg-danger">' . pg_format_money($ineligible_total / 100, BASE_CURRENCY_SYMBOL) . '</p>
                                    <hr/>
                                </div>
                                <div class="col-12 text-center ">
                                    <h5>' . lang('Paid Total') . '</h5>
                                    <p class="fw-bolder fg-success">' . pg_format_money($paid_total / 100, BASE_CURRENCY_SYMBOL) . '</p>
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