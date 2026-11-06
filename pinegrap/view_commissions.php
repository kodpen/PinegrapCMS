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

// if advanced filters value was passed in the query string
if (isset($_REQUEST['advanced_filters']) == true) {
    $_SESSION['software']['ecommerce']['view_commissions']['advanced_filters'] = ($_REQUEST['advanced_filters'] == 'true');
}

// The range a month ago to today unless one was picked; a picked range is
// kept in the session (includes/fn/commission_list.php). The sort is kept by
// pg_list_state().
$range = pg_commission_list_range($_REQUEST);
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

// The list table driver prints the first rows; the next ones come from its
// list_table_rows action (includes/panel/lists.php) as the list scrolls,
// within the range kept above.
$list_sort = pg_commission_list_sort();
$sort_columns = $list_sort['sort_columns'];
$sort_default = $list_sort['sort_default'];
$sort_levels = $list_sort['sort_levels'];

$list_source = pg_commission_list_source($user, array());

if ($list_source === null) {
    output_error(lang('Access denied'));
}

$list_page = pg_list_table_page($list_source, array(), null);

if ($list_page === false) {
    output_error(lang('Query failed.'));
}

// The status totals of the range, from the commissions alone: a join on the
// affiliate code counted a commission once per contact holding the code.
$query =
    "SELECT status, SUM(amount) AS total
    FROM commissions
    WHERE (created_timestamp >= ?) AND (created_timestamp <= ?)
    GROUP BY status";
$result = db_q($query, array((string) $start_timestamp, (string) $stop_timestamp)) or output_error('Query failed.');
$status_rows = array();

while ($row = mysqli_fetch_assoc($result)) {
    $status_rows[] = $row;
}

$status_totals = pg_commission_list_totals($status_rows);
$pending_total = $status_totals['pending'];
$payable_total = $status_totals['payable'];
$ineligible_total = $status_totals['ineligible'];
$paid_total = $status_totals['paid'];


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
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            ' . pg_screen_toolbar(array(
                'search' => array(
                    '<label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search"><span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span><input type="search" class="form-ghost" data-pg-list-search="view_commissions" placeholder="' . h(lang('Search')) . '" aria-label="' . h(lang('Search')) . '"></label>',
                    pg_list_sort_menu('view_commissions.php', $sort_columns, $sort_levels, $sort_default),
                    '<a class="btn btn-sm btn-text theme-secondary' . $output_advanced_filters_class . '" title="' . $output_advanced_filters_label . '" aria-label="' . $output_advanced_filters_label . '" href="view_commissions.php?advanced_filters=' . $output_advanced_filters_value . '"><i class="bi ' . $advanced_filters_icon . '" aria-hidden="true"></i></a>',
                ),
            )) . '
            ' . $output_advanced_filters . '
            <div class="row g-5 my-3">
                <div class="col-6 md:col-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small fg-secondary">' . lang('Pending Total') . '</div>
                            <div class="fs-2xl fw-bold fg-body">' . pg_format_money($pending_total / 100, BASE_CURRENCY_SYMBOL) . '</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 md:col-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small fg-secondary">' . lang('Payable Total') . '</div>
                            <div class="fs-2xl fw-bold fg-primary">' . pg_format_money($payable_total / 100, BASE_CURRENCY_SYMBOL) . '</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 md:col-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small fg-secondary">' . lang('Ineligible Total') . '</div>
                            <div class="fs-2xl fw-bold fg-danger">' . pg_format_money($ineligible_total / 100, BASE_CURRENCY_SYMBOL) . '</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 md:col-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small fg-secondary">' . lang('Paid Total') . '</div>
                            <div class="fs-2xl fw-bold fg-success">' . pg_format_money($paid_total / 100, BASE_CURRENCY_SYMBOL) . '</div>
                        </div>
                    </div>
                </div>
            </div>
            ' . pg_list_table_html($list_source, $list_page, lang('All Commissions')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
    output_footer();

$liveform->remove_form();
?>