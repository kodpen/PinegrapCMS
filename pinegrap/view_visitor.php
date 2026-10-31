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
validate_visitors_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM visitors WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// If the base currency symbol is not defined, then that is because commerce
// is disabled, so we need to set a base currency.  This is the only area
// that shows currency when commerce is disabled.  In the future,
// we should probably consider not showing commerce data on this screen
// if commerce is disabled, however we are not going to spend time on that for now.
if (defined('BASE_CURRENCY_SYMBOL') == false) {
    define('BASE_CURRENCY_SYMBOL', '$');
}

// if the user was creating a visitor report on the previous screen, then prepare previous screen link
// (a report id sent as an array, ?visitor_report_id[]=x, counts as not given
// instead of ending in an error page)
if (!is_scalar($_GET['visitor_report_id'] ?? null)) {
    $pg_previous_screen_item = array('label' => lang('Visitor Report'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_report.php');
    $pg_back_arrow_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_report.php';

// else the user was editing a visitor report on the previous screen, so prepare previous screen link
} else {
    $pg_previous_screen_item = array('label' => lang('Visitor Report'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_report.php?id=' . h($_GET['visitor_report_id']));
    // The arrow url goes through pg_output_go_script(), so it is not h()-escaped.
    $pg_back_arrow_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_report.php?id=' . (int) $_GET['visitor_report_id'];
}

// get visitor information (a newer visit names its referring address in the
// list of visitor texts)
$sql_http_referer = pg_visitor_text_sql('http_referer');

$query =
    "SELECT
        visitors.id,
        IF(visitors.ip_address = 0, '', INET_NTOA(visitors.ip_address)) as ip_address,
        " . $sql_http_referer . " AS http_referer,
        referring_host_name,
        referring_search_engine,
        referring_search_terms,
        first_visit,
        landing_page_name,
        tracking_code,
        affiliate_code,
        currency_code,
        utm_source,
        utm_medium,
        utm_campaign,
        utm_term,
        utm_content,
        page_views,
        custom_form_submitted,
        custom_form_name,
        order_created,
        order_retrieved,
        order_checked_out,
        order_completed,
        order_total,
        city,
        state,
        zip_code,
        country,
        start_timestamp,
        stop_timestamp,
        site_search_terms
    FROM visitors" . pg_visitor_text_joins($sql_http_referer) . "
    WHERE visitors.id = '" . $id . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

$id = $row['id'];
$ip_address = (string) $row['ip_address'];
$http_referer = $row['http_referer'];
$referring_host_name = $row['referring_host_name'];
$referring_search_engine = $row['referring_search_engine'];
$referring_search_terms = $row['referring_search_terms'];
$first_visit = $row['first_visit'];
$landing_page_name = $row['landing_page_name'];
$tracking_code = $row['tracking_code'];
$affiliate_code = $row['affiliate_code'];
$currency_code = $row['currency_code'];
$utm_source = $row['utm_source'];
$utm_medium = $row['utm_medium'];
$utm_campaign = $row['utm_campaign'];
$utm_term = $row['utm_term'];
$utm_content = $row['utm_content'];
$page_views = $row['page_views'];
$custom_form_submitted = $row['custom_form_submitted'];
$custom_form_name = $row['custom_form_name'];
$order_created = $row['order_created'];
$order_retrieved = $row['order_retrieved'];
$order_checked_out = $row['order_checked_out'];
$order_completed = $row['order_completed'];
$order_total = $row['order_total'];
$city = $row['city'];
$state = $row['state'];
$zip_code = $row['zip_code'];
$country = $row['country'];
$start_timestamp = $row['start_timestamp'];
$stop_timestamp = $row['stop_timestamp'];
$site_search_terms = $row['site_search_terms'];

// The address is 0 in the row once the general job took it out after its
// period (includes/fn/visitor_ip.php), and for a visit it could not be stored
// for (IPv6): the first says so, the second shows a dash.
$ip_address_text = ($ip_address !== '') ? $ip_address : '-';
$visitor_ip_settings = pg_visitor_retention_settings();

if (($ip_address === '') && is_array($visitor_ip_settings) && ($visitor_ip_settings['ip_days'] > 0)
    && ((int) $start_timestamp < pg_visitor_retention_cutoff($visitor_ip_settings['ip_days'], time()))
) {
    $ip_address_text = lang(array('string' => 'Removed after {var:1} days', 'vars' => array($visitor_ip_settings['ip_days'])));
}

$output_currency_row = '';

// if multi-currency is enabled, then output currency row
if ((ECOMMERCE == true) && (ECOMMERCE_MULTICURRENCY == true)) {
    $currency_name_and_code = '';
    
    // if there is a currency code, then output it
    if ($currency_code != '') {
        $currency_name_and_code = get_currency_name_from_code($currency_code) . ' (' . $currency_code . ')';
    }
    
    $output_currency_row =
        '<div class="col-12 md:col-6 lg:col-4 my-3">
            <div class="form-text">' . lang('Currency') . '</div>
            <div class="h5">' . h($currency_name_and_code) . '</div>
        </div>';
}

// if http referer is too long, then shorten
if (mb_strlen($http_referer) > 100) {
    $output_http_referer = mb_substr($http_referer, 0, 100) . '...';
} else {
    $output_http_referer = $http_referer;
}

// if referring host name is too long, then shorten
if (mb_strlen($referring_host_name) > 100) {
    $output_referring_host_name = mb_substr($referring_host_name, 0, 100) . '...';
} else {
    $output_referring_host_name = $referring_host_name;
}

// if pay per click flag is in tracking code, this visitor is pay per click
if ((defined('PAY_PER_CLICK_FLAG') == true) && (PAY_PER_CLICK_FLAG != '') && (mb_strpos($tracking_code, PAY_PER_CLICK_FLAG) !== false)) {
    $output_pay_per_click_organic = lang('Pay Per Click');
    
// else if referring seraching engine is not blank, this visitor is organic
} elseif ($referring_search_engine != '') {
    $output_pay_per_click_organic = lang('Organic');
    
// else this visitor is neither
} else {
    $output_pay_per_click_organic = '';
}

$output_first_visit = ($first_visit) ? lang('Yes') : lang('No');

$output_affiliate_code = '';
if (AFFILIATE_PROGRAM == true) {
    $output_affiliate_code =
        '<div class="col-12 md:col-6 lg:col-4 my-3">
            <div class="form-text">' . lang('Affiliate Code') . '</div>
            <div class="h5">' . h($affiliate_code ? $affiliate_code : '-') . '</div>
        </div>';
}

$utm = '';

if ($utm_source) {

    $utm .=
        '<div class="col-12">
            <div class="card my-7">
               <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                    ' . lang('UTM') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 md:col-6 lg:col-4 my-3">
                            <div class="form-text">' . lang('Source') . '</div>
                            <div class="h5">' . h($utm_source) . '</div>
                        </div>';

    if ($utm_medium) {
        $utm .=
            '<div class="col-12 md:col-6 lg:col-4 my-3">
                <div class="form-text">' . lang('Medium') . '</div>
                <div class="h5">' . h($utm_medium) . '</div>
            </div>';
    }

    if ($utm_campaign) {
        $utm .=
            '<div class="col-12 md:col-6 lg:col-4 my-3">
                <div class="form-text">' . lang('Campaign') . '</div>
                <div class="h5">' . h($utm_campaign) . '</div>
            </div>';
    }

    if ($utm_term) {
        $utm .=
            '<div class="col-12 md:col-6 lg:col-4 my-3">
                <div class="form-text">' . lang('Term') . '</div>
                <div class="h5">' . h($utm_term) . '</div>
            </div>';
    }

    if ($utm_content) {
        $utm .=
            '<div class="col-12 md:col-6 lg:col-4 my-3">
                <div class="form-text">' . lang('Content') . '</div>
                <div class="h5">' . h($utm_content) . '</div>
            </div>';
    }
        $utm .= '</div>
            </div>
        </div>
    </div>';

}

$output_custom_form_submitted = ($custom_form_submitted) ? lang('Yes') : lang('No');
$output_order_created = ($order_created) ? lang('Yes') : lang('No');
$output_order_retrieved = ($order_retrieved) ? lang('Yes') : lang('No');
$output_order_checked_out = ($order_checked_out) ? lang('Yes') : lang('No');
$output_order_completed = ($order_completed) ? lang('Yes') : lang('No');

echo
pg_page_shell(
    array(
        'title'=> lang('Visit Details'),
        'extra classes'=>'visitor',
        'icon'=>'visitor',
        'heading'=> lang('Visit Details'),
        'heading_description' => lang('View this computer\'s visit to the website.'),
        'cancel'=>array('enable'=>'true','url' => $pg_back_arrow_url),
        'breadcrumb' => array(
            array('label' => lang('All Visitor Reports'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_reports.php'),
            $pg_previous_screen_item,
            array('label' => lang('Visit Details')),
        ),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="row mb-3  flex-wrap pg-print-none">
                <div class="col-12 sm:col-12 text-center md:text-start">

                    <p class="p-0 m-0">' . lang('Visitor\'s IP Address') . ': ' . h($ip_address_text) . '</p>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card my-7">
                       <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                            ' . lang('General Visit Information') . '
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Visitor Number') . '</div>
                                    <div class="h5">' . h($id ? $id : '-') . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Start Time') . '</div>
                                    <div class="h6">' . get_absolute_time(array('timestamp' => $start_timestamp)) . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('End Time') . '</div>
                                    <div class="h6">' . get_absolute_time(array('timestamp' => $stop_timestamp)) . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Site Search Terms') . '</div>
                                    <div class="h6">' . h($site_search_terms ? str_replace('|', ', ', $site_search_terms) : '-') . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('IP Address') . '</div>
                                    <div class="h5">' . h($ip_address_text) . '</div>
                                </div>
                                ' . $output_currency_row . '
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card my-7">
                       <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                            ' . lang('Referral Information') . '
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('URL') . '</div>
                                    ' . ($output_http_referer ? '<a class="pg-link-secondary text-break" href="' . h(escape_url($http_referer)) . '" target="_blank">' . h($output_http_referer) . '</a>' : '-') . '
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Host Name') . '</div>
                                    ' . ($output_referring_host_name ? '<a class="pg-link-secondary text-break" href="http://' . h($referring_host_name) . '" target="_blank">' . h($output_referring_host_name) . '</a>' : '-') . '
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Search Engine') . '</div>
                                    <div class="h5">' . h($referring_search_engine ? $referring_search_engine : '-') . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Search Terms') . '</div>
                                    <div class="h5">' . h($referring_search_terms ? $referring_search_terms : '-') . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Pay Per Click / Organic') . '</div>
                                    <div class="h5">' . h($output_pay_per_click_organic ? $output_pay_per_click_organic : '-') . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('First Visit') . '</div>
                                    <div class="h5">' . $output_first_visit . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Landing Page') . '</div>
                                    <a class="pg-link-secondary text-break" href="' . OUTPUT_PATH . h($landing_page_name) . '" target="_blank">' . h($landing_page_name) . '</a>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Tracking Code') . '</div>
                                    <div class="h5">' . h($tracking_code ? $tracking_code : '-') . '</div>
                                </div>
                                ' . $output_affiliate_code . '
                            </div>
                        </div>
                    </div>
                </div>
                ' . $utm . '
                <div class="col-12">
                    <div class="card my-7">
                       <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                            ' . lang('Actions performed during Visit') . '
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Page Views') . '</div>
                                    <div class="h4">' . pg_format_number($page_views, 0) . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Custom Form Submitted') . '</div>
                                    <div class="h5">' . $output_custom_form_submitted . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Custom Form') . '</div>
                                    <div class="h5">' . h($custom_form_name ? $custom_form_name : '-') . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Order Created') . '</div>
                                    <div class="h5">' . $output_order_created . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Order Retrieved') . '</div>
                                    <div class="h5">' . $output_order_retrieved . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Order Checked Out') . '</div>
                                    <div class="h5">' . $output_order_checked_out . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Order Completed') . '</div>
                                    <div class="h5">' . $output_order_completed . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Order Total') . '</div>
                                    <div class="h4">' . prepare_amount($order_total / 100) . '</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card my-7">
                       <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                            ' . lang('Visitor\'s Location Information') . '
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('City') . '</div>
                                    <div class="h5">' . h($city ? $city : '-') . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('State') . '</div>
                                    <div class="h5">' . h($state ? $state : '-') . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Zip Code') . '</div>
                                    <div class="h5">' . h($zip_code ? $zip_code : '-') . '</div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4 my-3">
                                    <div class="form-text">' . lang('Country') . '</div>
                                    <div class="h5">' . h($country ? $country : '-') . '</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' . output_footer();