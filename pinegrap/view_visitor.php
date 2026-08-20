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

// If the base currency symbol is not defined, then that is because commerce
// is disabled, so we need to set a base currency.  This is the only area
// that shows currency when commerce is disabled.  In the future,
// we should probably consider not showing commerce data on this screen
// if commerce is disabled, however we are not going to spend time on that for now.
if (defined('BASE_CURRENCY_SYMBOL') == false) {
    define('BASE_CURRENCY_SYMBOL', '$');
}

// if the user was creating a visitor report on the previous screen, then prepare previous screen link
if (isset($_GET['visitor_report_id']) == false) {
    $pg_previous_screen_item = array('label' => lang('Visitor Report'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_report.php');

// else the user was editing a visitor report on the previous screen, so prepare previous screen link
} else {
    $pg_previous_screen_item = array('label' => lang('Visitor Report'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_report.php?id=' . h($_GET['visitor_report_id']));
}

// get visitor information
$query =
    "SELECT
        id,
        INET_NTOA(ip_address) as ip_address,
        http_referer,
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
    FROM visitors
    WHERE id = '" . escape($_GET['id']) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

$id = $row['id'];
$ip_address = $row['ip_address'];
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

$output_currency_row = '';

// if multi-currency is enabled, then output currency row
if ((ECOMMERCE == true) && (ECOMMERCE_MULTICURRENCY == true)) {
    $currency_name_and_code = '';
    
    // if there is a currency code, then output it
    if ($currency_code != '') {
        $currency_name_and_code = get_currency_name_from_code($currency_code) . ' (' . $currency_code . ')';
    }
    
    $output_currency_row =
        '<div class="col-12 col-md-6 col-lg-4 my-2">
            <div class="form-text">' . lang('Currency') . '</div>
            <h5>' . h($currency_name_and_code) . '</h5>
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

if (AFFILIATE_PROGRAM == true) {
    $output_affiliate_code =
        '<div class="col-12 col-md-6 col-lg-4 my-2">
            <div class="form-text">' . lang('Affiliate Code') . '</div>
            <h5>' . h($affiliate_code ? $affiliate_code : '-') . '</h5>
        </div>';
}

$utm = '';

if ($utm_source) {

    $utm .=
        '<div class="col-12">
            <div class="card my-4">
               <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('UTM') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-md-6 col-lg-4 my-2">
                            <div class="form-text">' . lang('Source') . '</div>
                            <h5>' . h($utm_source) . '</h5>
                        </div>';

    if ($utm_medium) {
        $utm .=
            '<div class="col-12 col-md-6 col-lg-4 my-2">
                <div class="form-text">' . lang('Medium') . '</div>
                <h5>' . h($utm_medium) . '</h5>
            </div>';
    }

    if ($utm_campaign) {
        $utm .=
            '<div class="col-12 col-md-6 col-lg-4 my-2">
                <div class="form-text">' . lang('Campaign') . '</div>
                <h5>' . h($utm_campaign) . '</h5>
            </div>';
    }

    if ($utm_term) {
        $utm .=
            '<div class="col-12 col-md-6 col-lg-4 my-2">
                <div class="form-text">' . lang('Term') . '</div>
                <h5>' . h($utm_term) . '</h5>
            </div>';
    }

    if ($utm_content) {
        $utm .=
            '<div class="col-12 col-md-6 col-lg-4 my-2">
                <div class="form-text">' . lang('Content') . '</div>
                <h5>' . h($utm_content) . '</h5>
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
        'cancel'=>array('enable'=>'true','url'=>'view_visitor_reports.php'),
        'breadcrumb' => array(
            array('label' => lang('All Visitor Reports'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_reports.php'),
            $pg_previous_screen_item,
            array('label' => lang('Visit Details')),
        ),
    )
) . '
    <div class="row">
        <div class="col-12">
            <div class="row mb-2  flex-wrap d-print-none">
                <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('View this computer\'s visit to the website.') . '" title="' . lang('Visit Details') . '">[' . lang('Visitor') . ' #' . $id . ']</h2>
                    <p class="p-0 m-0">' . lang('Visitor\'s IP Address') . ': ' . $ip_address . '</p>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card my-4">
                       <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('General Visit Information') . '
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Visitor Number') . '</div>
                                    <h5>' . h($id ? $id : '-') . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Start Time') . '</div>
                                    <h6>' . get_absolute_time(array('timestamp' => $start_timestamp)) . '</h6>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('End Time') . '</div>
                                    <h6>' . get_absolute_time(array('timestamp' => $stop_timestamp)) . '</h6>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Site Search Terms') . '</div>
                                    <h6>' . h($site_search_terms ? str_replace('|', ', ', h($site_search_terms)) : '-') . '</h6>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('IP Address') . '</div>
                                    <h5>' . h($ip_address ? $ip_address : '-') . '</h5>
                                </div>
                                ' . $output_currency_row . '
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card my-4">
                       <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('Referral Information') . '
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('URL') . '</div>
                                    ' . h($output_http_referer ? '<a class="link-secondary" href="' . h(escape_url($http_referer)) . '" target="_blank">' . h($output_http_referer) . '</a>' : '-') . '
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Host Name') . '</div>
                                    ' . h($output_referring_host_name ? '<a class="link-secondary" href="http://' . h($referring_host_name) . '" target="_blank">' . h($output_referring_host_name) . '</a>' : '-') . '
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Search Engine') . '</div>
                                    <h5>' . h($referring_search_engine ? $referring_search_engine : '-') . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Search Terms') . '</div>
                                    <h5>' . h($referring_search_terms ? $referring_search_terms : '-') . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Pay Per Click / Organic') . '</div>
                                    <h5>' . h($output_pay_per_click_organic ? $output_pay_per_click_organic : '-') . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('First Visit') . '</div>
                                    <h5>' . $output_first_visit . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Landing Page') . '</div>
                                    <a class="link-secondary" href="' . OUTPUT_PATH . h($landing_page_name) . '" target="_blank">' . h($landing_page_name) . '</a>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Tracking Code') . '</div>
                                    <h5>' . h($tracking_code ? $tracking_code : '-') . '</h5>
                                </div>
                                ' . $output_affiliate_code . '
                            </div>
                        </div>
                    </div>
                </div>
                ' . $utm . '
                <div class="col-12">
                    <div class="card my-4">
                       <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('Actions performed during Visit') . '
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Page Views') . '</div>
                                    <h4>' . number_format($page_views) . '</h4>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Custom Form Submitted') . '</div>
                                    <h5>' . $output_custom_form_submitted . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Custom Form') . '</div>
                                    <h5>' . h($custom_form_name ? $custom_form_name : '-') . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Order Created') . '</div>
                                    <h5>' . $output_order_created . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Order Retrieved') . '</div>
                                    <h5>' . $output_order_retrieved . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Order Checked Out') . '</div>
                                    <h5>' . $output_order_checked_out . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Order Completed') . '</div>
                                    <h5>' . $output_order_completed . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Order Total') . '</div>
                                    <h4>' . prepare_amount($order_total / 100) . '</h4>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Order Completed') . '</div>
                                    <h5>' . $output_order_completed . '</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card my-4">
                       <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('Visitor\'s Location Information') . '
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('City') . '</div>
                                    <h5>' . h($city ? $city : '-') . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('State') . '</div>
                                    <h5>' . h($state ? $state : '-') . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Zip Code') . '</div>
                                    <h5>' . h($zip_code ? $zip_code : '-') . '</h5>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                    <div class="form-text">' . lang('Country') . '</div>
                                    <h5>' . h($country ? $country : '-') . '</h5>
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