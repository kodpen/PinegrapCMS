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

// only ever appended to further below, so it has to start out empty
$output_rows = '';
$output = '';
$where = '';

// These joins are only added for some of the advanced filters, so they have to start out empty.
$join_order_items = '';
$join_ship_tos = '';
$join_billing_states = '';
$join_billing_countries = '';
$join_shipping_states = '';
$join_shipping_countries = '';

$liveform = new liveform('view_orders');

$user = validate_user();
validate_ecommerce_access($user);

// If the reset parameter was sent in the query string,
// then clear all session values for this screen.
// The shipping report screen uses this feature in order to link to this screen
// with a fresh view.
if ((isset($_GET['reset'])) && ($_GET['reset'] == 'true')) {
    unset($_SESSION['software']['ecommerce']['view_orders']);
}

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ecommerce']['view_orders'][$key] = trim($value);
    }
}

// if advanced filters value was passed in the query string
if (isset($_REQUEST['advanced_filters']) == true) {
    // if advanced filters should be turned on
    if ($_REQUEST['advanced_filters'] == 'true') {
        $_SESSION['software']['ecommerce']['view_orders']['advanced_filters'] = true;

    // else advanced filters should be turned off
    } else {
        $_SESSION['software']['ecommerce']['view_orders']['advanced_filters'] = false;
    }
}


// Set default values for some fields.

if (isset($_SESSION['software']['ecommerce']['view_orders']['date_type']) == false) {
    $_SESSION['software']['ecommerce']['view_orders']['date_type'] = 'order_date';
}

if (isset($_SESSION['software']['ecommerce']['view_orders']['start_month']) == false) {
    $_SESSION['software']['ecommerce']['view_orders']['start_month'] = date('m', time() - 2678400);
    $_SESSION['software']['ecommerce']['view_orders']['start_day'] = date('d', time() - 2678400);
    $_SESSION['software']['ecommerce']['view_orders']['start_year'] = date('Y', time() - 2678400);

    $_SESSION['software']['ecommerce']['view_orders']['stop_month'] = date('m');
    $_SESSION['software']['ecommerce']['view_orders']['stop_day'] = date('d');
    $_SESSION['software']['ecommerce']['view_orders']['stop_year'] = date('Y');
}

if (isset($_SESSION['software']['ecommerce']['view_orders']['status']) == false) {
    $_SESSION['software']['ecommerce']['view_orders']['status'] = 'complete_or_exported';
}

// Default: show only online orders; local orders are shown when explicitly selected.
if (isset($_SESSION['software']['ecommerce']['view_orders']['type']) == false) {
    $_SESSION['software']['ecommerce']['view_orders']['type'] = 'online';
}

// The refund columns arrive with the 2026.1.27 upgrade. Probed once and reused
// by both the filter below and its pick-list entry further down, so an
// installation that has not been upgraded never offers an option that cannot
// work.
$refund_columns_exist = (bool) db_item("SHOW COLUMNS FROM orders LIKE 'refund_status'");

$sql_status = "";

// Prepare SQL status filter differently based on the selected status.
switch (($_SESSION['software']['ecommerce']['view_orders']['status'] ?? '')) {
    case 'any':
        $sql_status = "";
        break;

    case 'incomplete':
        $sql_status = "AND (orders.status = 'incomplete')";
        break;

    case 'complete':
        $sql_status = "AND (orders.status = 'complete')";
        break;

    case 'exported':
        $sql_status = "AND (orders.status = 'exported')";
        break;

    case 'canceled':
    case 'cancelled':
        // Accept both spellings in the session value for backward compat with
        // any bookmarked / linked filter URLs; the canonical enum (2026.1.26+)
        // is 'cancelled'.
        $sql_status = "AND (orders.status = 'cancelled')";
        break;

    case 'refund_pending':
        // Cancelled orders whose money has not gone back to the customer yet.
        // Not a value of orders.status — the order's own status stays
        // 'cancelled' — so this reads the refund column instead. 'pending' and
        // 'failed' belong here with 'manual_required': all three mean the
        // customer is still owed. Only 'refunded' and '' are settled.
        if ($refund_columns_exist) {
            $sql_status = "AND (orders.refund_status IN ('manual_required', 'failed', 'pending'))";
            break;
        }

        // Column not present, which means a filter kept in the session from a
        // newer build. Falls through to the default view rather than quietly
        // listing something that means something else.
        // no break

    case 'complete_or_exported':
    default:
        $sql_status = "AND ((orders.status = 'complete') OR (orders.status = 'exported'))";
        break;
}

// Prepare SQL type filter.
$sql_type = "";
switch (($_SESSION['software']['ecommerce']['view_orders']['type'] ?? '')) {
    case 'online':
        $sql_type = "AND (orders.type = 'online')";
        break;

    case 'local':
        $sql_type = "AND (orders.type = 'local')";
        break;

    case 'any':
    default:
        $sql_type = "";
        break;
}

$decrease_year['start_month'] = '01';
$decrease_year['start_day'] = '01';
$decrease_year['start_year'] = ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? '') - 1;
$decrease_year['stop_month'] = '12';
$decrease_year['stop_day'] = '31';
$decrease_year['stop_year'] = ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? '') - 1;

$current_year['start_month'] = '01';
$current_year['start_day'] = '01';
$current_year['start_year'] = date('Y');
$current_year['stop_month'] = '12';
$current_year['stop_day'] = '31';
$current_year['stop_year'] = date('Y');

$increase_year['start_month'] = '01';
$increase_year['start_day'] = '01';
$increase_year['start_year'] = ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? '') + 1;
$increase_year['stop_month'] = '12';
$increase_year['stop_day'] = '31';
$increase_year['stop_year'] = ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? '') + 1;

$decrease_month['new_time'] = mktime(0, 0, 0, ($_SESSION['software']['ecommerce']['view_orders']['start_month'] ?? '') - 1, 1, ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? ''));
$decrease_month['new_month'] = date('m', $decrease_month['new_time']);
$decrease_month['new_year'] = date('Y', $decrease_month['new_time']);
$decrease_month['start_month'] = $decrease_month['new_month'];
$decrease_month['start_day'] = '01';
$decrease_month['start_year'] = $decrease_month['new_year'];
$decrease_month['stop_month'] = $decrease_month['new_month'];
$decrease_month['stop_day'] = date('t', $decrease_month['new_time']);
$decrease_month['stop_year'] = $decrease_month['new_year'];

$current_month['new_month'] = date('m');
$current_month['new_year'] = date('Y');
$current_month['start_month'] = $current_month['new_month'];
$current_month['start_day'] = '01';
$current_month['start_year'] = $current_month['new_year'];
$current_month['stop_month'] = $current_month['new_month'];
$current_month['stop_day'] = date('t');
$current_month['stop_year'] = $current_month['new_year'];

$increase_month['new_time'] = mktime(0, 0, 0, ($_SESSION['software']['ecommerce']['view_orders']['start_month'] ?? '') + 1, 1, ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? ''));
$increase_month['new_month'] = date('m', $increase_month['new_time']);
$increase_month['new_year'] = date('Y', $increase_month['new_time']);
$increase_month['start_month'] = $increase_month['new_month'];
$increase_month['start_day'] = '01';
$increase_month['start_year'] = $increase_month['new_year'];
$increase_month['stop_month'] = $increase_month['new_month'];
$increase_month['stop_day'] = date('t', $increase_month['new_time']);
$increase_month['stop_year'] = $increase_month['new_year'];

$decrease_week['start_date_timestamp'] = mktime(0, 0, 0, ($_SESSION['software']['ecommerce']['view_orders']['start_month'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['start_day'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? ''));
// if start date is a Sunday, use last Sunday (add 12:00:00 to prevent a bug that results in Saturday being returned)
if (date('l', $decrease_week['start_date_timestamp']) == 'Sunday') {
    $decrease_week['new_time_start'] = strtotime('last sunday 12:00:00', $decrease_week['start_date_timestamp']);

// else start date is not a Sunday, so we need to do last sunday twice (add 12:00:00 to prevent a bug that results in Saturday being returned)
} else {
    $decrease_week['new_time_start'] = strtotime('last sunday 12:00:00', $decrease_week['start_date_timestamp']);
    $decrease_week['new_time_start'] = strtotime('last sunday 12:00:00', $decrease_week['new_time_start']);
}
$decrease_week['new_time_stop'] = strtotime('Saturday', $decrease_week['new_time_start']);
$decrease_week['start_month'] = date('m', $decrease_week['new_time_start']);
$decrease_week['start_day'] = date('d', $decrease_week['new_time_start']);
$decrease_week['start_year'] = date('Y', $decrease_week['new_time_start']);
$decrease_week['stop_month'] = date('m', $decrease_week['new_time_stop']);
$decrease_week['stop_day'] = date('d', $decrease_week['new_time_stop']);
$decrease_week['stop_year'] = date('Y', $decrease_week['new_time_stop']);

// if today is Sunday
if (date('l') == 'Sunday') {
    $current_week['new_time_start'] = strtotime('Sunday');
} else {
    $current_week['new_time_start'] = strtotime('last Sunday');
}
$current_week['new_time_stop'] = strtotime('Saturday', $current_week['new_time_start']);
$current_week['start_month'] = date('m', $current_week['new_time_start']);
$current_week['start_day'] = date('d', $current_week['new_time_start']);
$current_week['start_year'] = date('Y', $current_week['new_time_start']);
$current_week['stop_month'] = date('m', $current_week['new_time_stop']);
$current_week['stop_day'] = date('d', $current_week['new_time_stop']);
$current_week['stop_year'] = date('Y', $current_week['new_time_stop']);

$increase_week['start_date_timestamp'] = mktime(0, 0, 0, ($_SESSION['software']['ecommerce']['view_orders']['start_month'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['start_day'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? ''));
// if start date is a Sunday
if (date('l', $increase_week['start_date_timestamp']) == 'Sunday') {
    $increase_week['new_time_start'] = strtotime('2 Sunday', $increase_week['start_date_timestamp']);
} else {
    $increase_week['new_time_start'] = strtotime('Sunday', $increase_week['start_date_timestamp']);
}
$increase_week['new_time_stop'] = strtotime('Saturday', $increase_week['new_time_start']);
$increase_week['start_month'] = date('m', $increase_week['new_time_start']);
$increase_week['start_day'] = date('d', $increase_week['new_time_start']);
$increase_week['start_year'] = date('Y', $increase_week['new_time_start']);
$increase_week['stop_month'] = date('m', $increase_week['new_time_stop']);
$increase_week['stop_day'] = date('d', $increase_week['new_time_stop']);
$increase_week['stop_year'] = date('Y', $increase_week['new_time_stop']);

$decrease_day['new_time'] = mktime(0, 0, 0, ($_SESSION['software']['ecommerce']['view_orders']['start_month'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['start_day'] ?? '') - 1, ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? ''));
$decrease_day['new_month'] = date('m', $decrease_day['new_time']);
$decrease_day['new_day'] = date('d', $decrease_day['new_time']);
$decrease_day['new_year'] = date('Y', $decrease_day['new_time']);
$decrease_day['start_month'] = $decrease_day['new_month'];
$decrease_day['start_day'] = $decrease_day['new_day'];
$decrease_day['start_year'] = $decrease_day['new_year'];
$decrease_day['stop_month'] = $decrease_day['new_month'];
$decrease_day['stop_day'] = $decrease_day['new_day'];
$decrease_day['stop_year'] = $decrease_day['new_year'];

$current_day['new_month'] = date('m');
$current_day['new_day'] = date('d');
$current_day['new_year'] = date('Y');
$current_day['start_month'] = $current_day['new_month'];
$current_day['start_day'] = $current_day['new_day'];
$current_day['start_year'] = $current_day['new_year'];
$current_day['stop_month'] = $current_day['new_month'];
$current_day['stop_day'] = $current_day['new_day'];
$current_day['stop_year'] = $current_day['new_year'];

$increase_day['new_time'] = mktime(0, 0, 0, ($_SESSION['software']['ecommerce']['view_orders']['start_month'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['start_day'] ?? '') + 1, ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? ''));
$increase_day['new_month'] = date('m', $increase_day['new_time']);
$increase_day['new_day'] = date('d', $increase_day['new_time']);
$increase_day['new_year'] = date('Y', $increase_day['new_time']);
$increase_day['start_month'] = $increase_day['new_month'];
$increase_day['start_day'] = $increase_day['new_day'];
$increase_day['start_year'] = $increase_day['new_year'];
$increase_day['stop_month'] = $increase_day['new_month'];
$increase_day['stop_day'] = $increase_day['new_day'];
$increase_day['stop_year'] = $increase_day['new_year'];

// get timestamps for start and stop dates
$start_timestamp = mktime(0, 0, 0, ($_SESSION['software']['ecommerce']['view_orders']['start_month'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['start_day'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? ''));
$stop_timestamp = mktime(23, 59, 59, ($_SESSION['software']['ecommerce']['view_orders']['stop_month'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['stop_day'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['stop_year'] ?? ''));

// Output start date range time
$output_date_range_time = h(get_month_name_from_number(($_SESSION['software']['ecommerce']['view_orders']['start_month'] ?? '')) . ' ' . ($_SESSION['software']['ecommerce']['view_orders']['start_day'] ?? '') . ', ' . ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? ''));
$output_date_range_time .= ' - ';

// Output end date range time
$output_date_range_time .= h(get_month_name_from_number(($_SESSION['software']['ecommerce']['view_orders']['stop_month'] ?? '')) . ' ' . ($_SESSION['software']['ecommerce']['view_orders']['stop_day'] ?? '') . ', ' . ($_SESSION['software']['ecommerce']['view_orders']['stop_year'] ?? ''));

// if advanced filters are not set yet, then default them to off
if (isset($_SESSION['software']['ecommerce']['view_orders']['advanced_filters']) == false) {
    $_SESSION['software']['ecommerce']['view_orders']['advanced_filters'] = false;
}

// If the advanced filters are disabled or the date type is set to order date,
// then prepare SQL filter for order date.
if (
    (($_SESSION['software']['ecommerce']['view_orders']['advanced_filters'] ?? '') == false)
    || (($_SESSION['software']['ecommerce']['view_orders']['date_type'] ?? '') == 'order_date')
) {
    $where = "WHERE (orders.order_date >= $start_timestamp) AND (orders.order_date <= $stop_timestamp)";

// Otherwise the advanced filters are enabled and the date type is ship date,
// so prepare SQL filter for ship date.
} else {
    $where = "WHERE (ship_tos.ship_date >= '" . date('Y-m-d', $start_timestamp) . "') AND (ship_tos.ship_date <= '" . date('Y-m-d', $stop_timestamp) . "')";
}


// if advanced filters are on, prepare SQL
if (($_SESSION['software']['ecommerce']['view_orders']['advanced_filters'] ?? '') == true) {
    if (($_SESSION['software']['ecommerce']['view_orders']['order_number'] ?? '')) {$where .= " AND (orders.order_number LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['order_number'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['transaction_id'] ?? '')) {$where .= " AND (orders.transaction_id LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['transaction_id'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['authorization_code'] ?? '')) {$where .= " AND (orders.authorization_code LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['authorization_code'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['special_offer_code'] ?? '')) {$where .= " AND (orders.special_offer_code LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['special_offer_code'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['referral_source_code'] ?? '')) {$where .= " AND (orders.referral_source_code LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['referral_source_code'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['reference_code'] ?? '')) {$where .= " AND (orders.reference_code LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['reference_code'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['tracking_code'] ?? '')) {$where .= " AND (orders.tracking_code LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['tracking_code'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['http_referer'] ?? '')) {$where .= " AND (orders.http_referer LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['http_referer'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['ip_address'] ?? '')) {$where .= " AND (INET_NTOA(orders.ip_address) LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['ip_address'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['product_name'] ?? '')) {$where .= " AND (order_items.product_name LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['product_name'] ?? '')) . "%')";}

    // If payment method has not been set yet, then set it to any by default.
    if (isset($_SESSION['software']['ecommerce']['view_orders']['payment_method']) == false) {
        $_SESSION['software']['ecommerce']['view_orders']['payment_method'] = 'any';
    }

    if ((($_SESSION['software']['ecommerce']['view_orders']['payment_method'] ?? '') == '') || (($_SESSION['software']['ecommerce']['view_orders']['payment_method'] ?? '') == 'Credit/Debit Card') || (($_SESSION['software']['ecommerce']['view_orders']['payment_method'] ?? '') == 'PayPal Express Checkout') || (($_SESSION['software']['ecommerce']['view_orders']['payment_method'] ?? '') == 'Offline Payment')) {
        $where .= " AND (orders.payment_method = '" . escape(($_SESSION['software']['ecommerce']['view_orders']['payment_method'] ?? '')) . "')";
    }

    if (($_SESSION['software']['ecommerce']['view_orders']['card_type'] ?? '')) {$where .= " AND (orders.card_type LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['card_type'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['cardholder'] ?? '')) {$where .= " AND (orders.cardholder LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['cardholder'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['card_number'] ?? '')) {$where .= " AND (orders.card_number LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['card_number'] ?? '')) . "%')";}

    if (AFFILIATE_PROGRAM == true) {
        if (($_SESSION['software']['ecommerce']['view_orders']['affiliate_code'] ?? '')) {$where .= " AND (orders.affiliate_code LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['affiliate_code'] ?? '')) . "%')";}
    }

    if (($_SESSION['software']['ecommerce']['view_orders']['custom_field_1'] ?? '')) {$where .= " AND (orders.custom_field_1 LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['custom_field_1'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['custom_field_2'] ?? '')) {$where .= " AND (orders.custom_field_2 LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['custom_field_2'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_salutation'] ?? '')) {$where .= " AND (orders.billing_salutation LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_salutation'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_first_name'] ?? '')) {$where .= " AND (orders.billing_first_name LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_first_name'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_last_name'] ?? '')) {$where .= " AND (orders.billing_last_name LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_last_name'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_company'] ?? '')) {$where .= " AND (orders.billing_company LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_company'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_address_1'] ?? '')) {$where .= " AND (orders.billing_address_1 LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_address_1'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_address_2'] ?? '')) {$where .= " AND (orders.billing_address_2 LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_address_2'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_city'] ?? '')) {$where .= " AND (orders.billing_city LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_city'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_state'] ?? '')) {$where .= " AND ((orders.billing_state LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_state'] ?? '')) . "%') OR (billing_states.name LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_state'] ?? '')) . "%'))";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_zip_code'] ?? '')) {$where .= " AND (orders.billing_zip_code LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_zip_code'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_country'] ?? '')) {$where .= " AND ((orders.billing_country LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_country'] ?? '')) . "%') OR (billing_countries.name LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_country'] ?? '')) . "%'))";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_phone_number'] ?? '')) {$where .= " AND (orders.billing_phone_number LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_phone_number'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_fax_number'] ?? '')) {$where .= " AND (orders.billing_fax_number LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_fax_number'] ?? '')) . "%')";}
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_email_address'] ?? '')) {$where .= " AND (orders.billing_email_address LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['billing_email_address'] ?? '')) . "%')";}

    if (($_SESSION['software']['ecommerce']['view_orders']['opt_in_status'] ?? '') == 'opt_in') {
        $where .= " AND (orders.opt_in = '1')";
    } else if (($_SESSION['software']['ecommerce']['view_orders']['opt_in_status'] ?? '') == 'opt_out') {
        $where .= " AND (orders.opt_in = '0')";
    }

    if (($_SESSION['software']['ecommerce']['view_orders']['po_number'] ?? '')) {$where .= " AND (orders.po_number LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['po_number'] ?? '')) . "%')";}

    if (($_SESSION['software']['ecommerce']['view_orders']['tax_status'] ?? '') == 'tax_exempt') {
        $where .= " AND (orders.tax_exempt = '1')";
    } else if (($_SESSION['software']['ecommerce']['view_orders']['tax_status'] ?? '') == 'not_tax_exempt') {
        $where .= " AND (orders.tax_exempt = '0')";
    }

    if (($_SESSION['software']['ecommerce']['view_orders']['ship_to_name'] ?? '')) {$where .= " AND (ship_tos.ship_to_name LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['ship_to_name'] ?? '')) . "%')"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_salutation'] ?? '')) {$where .= " AND (ship_tos.salutation LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_salutation'] ?? '')) . "%')"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_first_name'] ?? '')) {$where .= " AND (ship_tos.first_name LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_first_name'] ?? '')) . "%')"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_last_name'] ?? '')) {$where .= " AND (ship_tos.last_name LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_last_name'] ?? '')) . "%')"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_company'] ?? '')) {$where .= " AND (ship_tos.company LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_company'] ?? '')) . "%')"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_address_1'] ?? '')) {$where .= " AND (ship_tos.address_1 LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_address_1'] ?? '')) . "%')"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_address_2'] ?? '')) {$where .= " AND (ship_tos.address_2 LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_address_2'] ?? '')) . "%')"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_city'] ?? '')) {$where .= " AND (ship_tos.city LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_city'] ?? '')) . "%')"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_state'] ?? '')) {$where .= " AND ((ship_tos.state LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_state'] ?? '')) . "%') OR (shipping_states.name LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_state'] ?? '')) . "%'))"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_zip_code'] ?? '')) {$where .= " AND (ship_tos.zip_code LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_zip_code'] ?? '')) . "%')"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_country'] ?? '')) {$where .= " AND ((ship_tos.country LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_country'] ?? '')) . "%') OR (shipping_countries.name LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_country'] ?? '')) . "%'))"; $shipping = true;}
    
    // If address type has not been set yet, then set it to any by default.
    if (isset($_SESSION['software']['ecommerce']['view_orders']['address_type']) == false) {
        $_SESSION['software']['ecommerce']['view_orders']['address_type'] = 'any';
    }

    if ((($_SESSION['software']['ecommerce']['view_orders']['address_type'] ?? '') == '') || (($_SESSION['software']['ecommerce']['view_orders']['address_type'] ?? '') == 'residential') || (($_SESSION['software']['ecommerce']['view_orders']['address_type'] ?? '') == 'business')) {
        $where .= " AND (ship_tos.address_type = '" . escape(($_SESSION['software']['ecommerce']['view_orders']['address_type'] ?? '')) . "')";
        $shipping = true;
    }
    
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_phone_number'] ?? '')) {$where .= " AND (ship_tos.phone_number LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_phone_number'] ?? '')) . "%')"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['arrival_date_code'] ?? '')) {$where .= " AND (ship_tos.arrival_date_code LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['arrival_date_code'] ?? '')) . "%')"; $shipping = true;}
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_method_code'] ?? '')) {$where .= " AND (ship_tos.shipping_method_code LIKE '%" . escape(($_SESSION['software']['ecommerce']['view_orders']['shipping_method_code'] ?? '')) . "%')"; $shipping = true;}

    // If shipping status has not been set yet, then set it to any by default.
    if (isset($_SESSION['software']['ecommerce']['view_orders']['shipping_status']) == false) {
        $_SESSION['software']['ecommerce']['view_orders']['shipping_status'] = 'any';
    }

    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_status'] ?? '') == 'shipped') {
        $where .=
            " AND
            ((SELECT COUNT(*)
            FROM order_items
            WHERE
                (order_items.order_id = orders.id)
                AND (order_items.ship_to_id != '0')
                AND (order_items.quantity > order_items.shipped_quantity)) = 0)";

    } else if (($_SESSION['software']['ecommerce']['view_orders']['shipping_status'] ?? '') == 'unshipped') {
        $where .=
            " AND
            ((SELECT COUNT(*)
            FROM order_items
            WHERE
                (order_items.order_id = orders.id)
                AND (order_items.ship_to_id != '0')
                AND (order_items.quantity > order_items.shipped_quantity)) > 0)";
    }

    if ((ECOMMERCE_MULTICURRENCY === true) && (($_SESSION['software']['ecommerce']['view_orders']['currency_code'] ?? ''))) {$where .= " AND (orders.currency_code = '" . escape(($_SESSION['software']['ecommerce']['view_orders']['currency_code'] ?? '')) . "')";}

    if (($_SESSION['software']['ecommerce']['view_orders']['date_type'] ?? '') == 'ship_date') {
        $shipping = true;
    }

    // if user is searching by product name, add a left join
    if (($_SESSION['software']['ecommerce']['view_orders']['product_name'] ?? '')) {
        $join_order_items = " LEFT JOIN order_items ON orders.id = order_items.order_id";
    }

    // if user is searching by a shipping field, add a left join
    if ($shipping == true) {
        $join_ship_tos = " LEFT JOIN ship_tos ON orders.id = ship_tos.order_id";
    }

    // if user is searching by billing state, add a left join
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_state'] ?? '')) {
        $join_billing_states = " LEFT JOIN states as billing_states ON orders.billing_state = billing_states.code";
    }

    // if user is searching by billing country, add a left join
    if (($_SESSION['software']['ecommerce']['view_orders']['billing_country'] ?? '')) {
        $join_billing_countries = " LEFT JOIN countries as billing_countries ON orders.billing_country = billing_countries.code";
    }

    // if user is searching by shipping state, add a left join
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_state'] ?? '')) {
        $join_shipping_states = " LEFT JOIN states as shipping_states ON ship_tos.state = shipping_states.code";
    }

    // if user is searching by shipping country, add a left join
    if (($_SESSION['software']['ecommerce']['view_orders']['shipping_country'] ?? '')) {
        $join_shipping_countries = " LEFT JOIN countries as shipping_countries ON ship_tos.country = shipping_countries.code";
    }
}

//if parasut configuration enabled from site settings.
//output export for parasut.
$output_generate_xlsl_button = '';
$output_xlsl_setup_modal = '';
if(defined('ENABLE_PARASUT') && ENABLE_PARASUT != 0){
    $output_xlsl_setup_modal = 
    '<div id="xlsl_setup_modal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">' . lang('Options & Confirmation') . '</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 my-2">
                            <label class="form-label" for="tax_rate_included_for_parasut">' . lang('Product Tax Type') . '</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="tax_rate_included_for_parasut" name="tax_rate_included_for_parasut" value="1" />
                                <label class="form-check-label" for="tax_rate_included_for_parasut">' . lang('Product taxes included in the product price') . '</label>
                                <div class="form-text text-info">' . lang('If you are determining product prices by adding tax amounts, select this checkbox.') . '</div>
                            </div>
                        </div>
                        <div class="col-12 my-1">
                            <label class="form-label" for="tax_rate_for_parasut">' . lang('Products Tax Rate') . '</label>
                            <div class="input-group w-auto">
                                <input type="number" id="tax_rate_for_parasut" name="tax_rate_for_parasut" class="form-control text-end pe-3" value="20" placeholder="18" />
                                <div class="input-group-text"> %</div>
                            </div>
                            <div class="form-text text-end">' . lang('Each product is processed at its tax-deducted price and tax rates are processed in a separate column.') . '</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                <p>' . lang('Do you approve to create 2 excel files suitable for using the selected orders in Paraşut application?') . '</p>
                    <button type="button" class="no-submit btn btn-secondary" data-bs-dismiss="modal">' . lang('Close') . '</button>
                    <button type="button" name="submit_data" value="Export Orders For Parasut" class="btn btn-success" data-loading-content="' . lang(array('string'=>'Generating') ) . '"><span class="material-icons me-2">done</span>' . lang(array('string'=>'Confirm') ) . '</button>
                </div>
            </div>
        </div>
    </div>';
    $output_generate_xlsl_button = '<button type="button" class="no-submit btn mb-1 mt-1 btn-success disabled" data-bs-toggle="modal" data-bs-target="#xlsl_setup_modal"><span class="material-icons me-2">file_present</span>' . lang(array('string'=>'Export for Parasut Selected') ) . '</button>';
}

// if user requested to export orders, export orders
if (($_GET['submit_data'] ?? '') == 'Export Orders (multiple files)') {
    $orders = array();
    
    // Prepare array in order to store which orders will appear in this report
    // we will use this later for dealing with custom shipping/billing form data.
    $order_ids = array();

    // get order data
    $query = "SELECT
                orders.*,
                INET_NTOA(ip_address) as ip_address,
                contacts.member_id
             FROM orders
             LEFT JOIN user ON orders.user_id = user.user_id
             LEFT JOIN contacts on orders.contact_id = contacts.id
             $join_order_items
             $join_ship_tos
             $join_billing_states
             $join_billing_countries
             $join_shipping_states
             $join_shipping_countries
             $where
             $sql_status
             $sql_type
             ORDER BY orders.id";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
        $order_ids[] = $row['id'];
    }

    // Initialize array that will be used for storing custom billing form data for all orders.
    $custom_billing_form_data = array();
    
    // Initialize array that will be used for storing custom field names for custom billing form data.
    $custom_billing_field_names = array();

    // Get all custom billing form data in database.
    $fields = db_items(
        "SELECT
            order_id,
            data,
            name
        FROM form_data
        WHERE
            (order_id != '0')
            AND (ship_to_id = '0')
            AND (order_item_id = '0')
        ORDER BY id ASC");

    // Loop through the fields in order to determine custom field names
    // and add fields to orders array for custom billing form data.
    foreach ($fields as $field) {
        // If this field is for an order that will appear in this export then deal with this field.
        if (in_array($field['order_id'], $order_ids) == true) {
            // If this field name has not been added to the custom field names array, then add it,
            // so we can keep track of all necessary field names
            if (in_array($field['name'], $custom_billing_field_names) == false) {
                $custom_billing_field_names[] = $field['name'];
            }
            
            // If there is not already form data for this field, then just add data to the array.
            if ((isset($custom_billing_form_data[$field['order_id']][$field['name']]) == FALSE) || ($custom_billing_form_data[$field['order_id']][$field['name']] == '')) {
                $custom_billing_form_data[$field['order_id']][$field['name']] = $field['data'];
                
            // Otherwise there is already form data for this field, so this is probably a field that supports multiple values,
            // so just append this additional value.
            } else {
                $custom_billing_form_data[$field['order_id']][$field['name']] .= ', ' . $field['data'];
            }
        }
    }

    // if multi currency is enabled
    if (ECOMMERCE_MULTICURRENCY === true) {
        $multi_currency_column_name = '"currency_code",';
    } else {
        $multi_currency_column_name = '';
    }

    // prepare first row of orders.csv data
    $order_data =
        '"order_id",' .
        '"order_number",' .
        '"custom_field_1",' .
        '"custom_field_2",' .
        '"billing_salutation",' .
        '"billing_first_name",' .
        '"billing_last_name",' .
        '"billing_email_address",' .
        '"billing_company",' .
        '"billing_address_1",' .
        '"billing_address_2",' .
        '"billing_city",' .
        '"billing_state",' .
        '"billing_zip_code",' .
        '"billing_country",' .
        '"billing_phone_number",' .
        '"billing_fax_number",' .
        '"payment_method",' .
        '"card_type",' .
        '"cardholder",' .
        '"card_number",' .
        '"expiration_month",' .
        '"expiration_year",' .
        '"card_verification_number",' .
        '"referral_source_code",' .
        '"po_number",' .
        '"tax_exempt",' .
        '"opt_in",' .
        '"subtotal",' .
        '"discount",' .
        '"tax",' .
        '"shipping",' .
        '"gift_card_discount",' .
        '"surcharge",' .
        '"total",' .
        '"commission",' .
        '"order_date",' .
        '"transaction_id",' .
        '"authorization_code",' .
        '"special_offer_code",' .
        '"reference_code",' .
        '"tracking_code",' .
        '"utm_source",' .
        '"utm_medium",' .
        '"utm_campaign",' .
        '"utm_term",' .
        '"utm_content",' .
        '"member_id",' .
        '"affiliate_code",' .
        $multi_currency_column_name .
        '"http_referer",' .
        '"ip_address"';

    // Loop through the custom billing field names in order to
    // output them in the first row of the orders.csv data.
    foreach ($custom_billing_field_names as $custom_billing_field_name) {
        $order_data .= ',"' . str_replace('"', '""', $custom_billing_field_name) . '"';
    }
    
    $order_data .= "\n";

    // Loop through all orders in order to prepare CSV rows.
    foreach ($orders as $order) {
        // for each value in the row
        foreach ($order as $key => $value) {
           // replace quotation mark with two quotation marks
           $value = str_replace('"', '""', $value);
           // set new value
           $order[$key] = $value;
        }
        
        $card_number = $order['card_number'];
        
        // if the credit card number is encrypted
        if ((mb_substr($card_number, 0, 1) != '*') && (mb_strlen($card_number) > 16)) {
            // if encryption is enabled, then decrypt the credit card number
            if (
                (defined('ENCRYPTION_KEY') == TRUE)
                && (extension_loaded('mcrypt') == TRUE)
                && (in_array('rijndael-256', mcrypt_list_algorithms()) == TRUE)
            ) {
                $card_number = decrypt_credit_card_number($card_number, ENCRYPTION_KEY);
                
                // if the credit card number is not numeric, then there was a decryption error
                if (is_numeric($card_number) == FALSE) {
                    $card_number = '[decryption error]';

                // else the credit card number was decrypted successfully,
                // so if the user does not have access to view card data,
                // then protect the credit card number
                } else if (($user['role'] == 3) && ($user['view_card_data'] == FALSE)) {
                    $card_number = protect_credit_card_number($card_number);
                }
                
            // else encryption is disabled, so output error
            } else {
                $card_number = '[decryption error]';
            }
        }
        
        $card_verification_number = $order['card_verification_number'];
        
        // if the card verification number is not already protected,
        // and the user does not have access to view card data,
        // then protect it
        if (
            (mb_substr($card_verification_number, 0, 1) != '*')
            && (($user['role'] == 3) && ($user['view_card_data'] == FALSE))
        ) {
            $card_verification_number = protect_card_verification_number($card_verification_number);
        }

        if ($order['tax_exempt']) {
            $tax_exempt = 'yes';
        } else {
            $tax_exempt = 'no';
        }

        if ($order['opt_in']) {
            $opt_in = 'yes';
        } else {
            $opt_in = 'no';
        }

        // If the date format is month and then day, then use that format.
        if (DATE_FORMAT == 'month_day') {
            $month_and_day_format = 'n/j';

        // Otherwise the date format is day and then month, so use that format.
        } else {
            $month_and_day_format = 'j/n';
        }

        // if multi currency is enabled
        if (ECOMMERCE_MULTICURRENCY === true) {
            $currency_code = '"' . $order['currency_code'] . '",';
        } else {
            $currency_code = '';
        }
        
        $ip_address = $order['ip_address'];
        
        // if the IP address is 0.0.0.0, then we don't know the IP address, so set the value to empty string
        if ($ip_address == '0.0.0.0') {
            $ip_address = '';
        }

        // prepare row for orders.csv data
        $order_data .=
            '"' . $order['id'] . '",' .
            '"' . $order['order_number'] . '",' .
            '"' . $order['custom_field_1'] . '",' .
            '"' . $order['custom_field_2'] . '",' .
            '"' . $order['billing_salutation'] . '",' .
            '"' . $order['billing_first_name'] . '",' .
            '"' . $order['billing_last_name'] . '",' .
            '"' . $order['billing_email_address'] . '",' .
            '"' . $order['billing_company'] . '",' .
            '"' . $order['billing_address_1'] . '",' .
            '"' . $order['billing_address_2'] . '",' .
            '"' . $order['billing_city'] . '",' .
            '"' . $order['billing_state'] . '",' .
            '"' . $order['billing_zip_code'] . '",' .
            '"' . $order['billing_country'] . '",' .
            '"' . $order['billing_phone_number'] . '",' .
            '"' . $order['billing_fax_number'] . '",' .
            '"' . $order['payment_method'] . '",' .
            '"' . $order['card_type'] . '",' .
            '"' . $order['cardholder'] . '",' .
            '"#' . $card_number . '",' .
            '"' . $order['expiration_month'] . '",' .
            '"' . $order['expiration_year'] . '",' .
            '"' . $card_verification_number . '",' .
            '"' . $order['referral_source_code'] . '",' .
            '"' . $order['po_number'] . '",' .
            '"' . $tax_exempt . '",' .
            '"' . $opt_in . '",' .
            '"' . sprintf("%01.2lf", $order['subtotal'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['discount'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['tax'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['shipping'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['gift_card_discount'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['surcharge'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['total'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['commission'] / 100) . '",' .
            '"' . date($month_and_day_format . '/Y g:i:s A T', $order['order_date']) . '",' .
            '"' . $order['transaction_id'] . '",' .
            '"' . $order['authorization_code'] . '",' .
            '"' . $order['special_offer_code'] . '",' .
            '"' . $order['reference_code'] . '",' .
            '"' . $order['tracking_code'] . '",' .
            '"' . $order['utm_source'] . '",' .
            '"' . $order['utm_medium'] . '",' .
            '"' . $order['utm_campaign'] . '",' .
            '"' . $order['utm_term'] . '",' .
            '"' . $order['utm_content'] . '",' .
            '"' . $order['member_id'] . '",' .
            '"' . $order['affiliate_code'] . '",' .
            $currency_code .
            '"' . $order['http_referer'] . '",' .
            '"' . $ip_address . '"';

        // Loop through the custom billing field names in order to
        // output custom billing form data for this order row.
        foreach ($custom_billing_field_names as $custom_billing_field_name) {
            $order_data .= ',"' . str_replace('"', '""', $custom_billing_form_data[$order['id']][$custom_billing_field_name]) . '"';
        }

        $order_data .= "\n";

        // If the status of this order is complete, then update the status to be exported.
        if ($order['status'] == 'complete') {
            db("UPDATE orders SET status = 'exported' WHERE id = '" . $order['id'] . "'");
        }
    }
    
    // get all custom shipping form data in database
    $query =
        "SELECT
            order_id,
            ship_to_id,
            data,
            name
        FROM form_data
        WHERE ship_to_id != '0'
        ORDER BY id ASC";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // initialize array that will be used for storing custom shipping form data for ship tos
    $ship_tos = array();
    
    // initialize array that will be used for storing custom field names for custom shipping form data
    $custom_field_names = array();
    
    // loop through the fields in order to determine custom field names and add fields to ship tos array for custom shipping form data
    while ($field = mysqli_fetch_assoc($result)) {
        // if this field is for an order that will appear in this export then deal with this field
        if (in_array($field['order_id'], $order_ids) == TRUE) {
            // if this field name has not been added to the custom field names array, then add it,
            // so we can keep track of all necessary field names
            if (in_array($field['name'], $custom_field_names) == FALSE) {
                $custom_field_names[] = $field['name'];
            }
            
            // if there is not already form data for this field in the ship tos array, then just add data to the array
            if ((isset($ship_tos[$field['ship_to_id']][$field['name']]) == FALSE) || ($ship_tos[$field['ship_to_id']][$field['name']] == '')) {
                $ship_tos[$field['ship_to_id']][$field['name']] = $field['data'];
                
            // else there is already form data for this field, so this is probably a field that supports multiple values,
            // so just append this additional value
            } else {
                $ship_tos[$field['ship_to_id']][$field['name']] .= ', ' . $field['data'];
            }
        }
    }

    // prepare first row of ship_tos.csv data
    $ship_to_data =
        '"ship_to_id",' .
        '"order_id",' .
        '"order_number",' .
        '"ship_to_name",' .
        '"salutation",' .
        '"first_name",' .
        '"last_name",' .
        '"company",' .
        '"address_1",' .
        '"address_2",' .
        '"city",' .
        '"state",' .
        '"zip_code",' .
        '"country",' .
        '"address_type",' .
        '"arrival_date_code",' .
        '"arrival_date",' .
        '"ship_date",' .
        '"delivery_date",' .
        '"shipping_method_code",' .
        '"shipping_cost"';
    
    // loop through the custom field names in order to output them in the first row of ship_tos.csv data
    foreach ($custom_field_names as $custom_field_name) {
        $ship_to_data .= ',"' . str_replace('"', '""', $custom_field_name) . '"';
    }
    
    $ship_to_data .= "\n";

    // get ship to data
    $query =
        "SELECT
            ship_tos.*,
            orders.order_number
        FROM ship_tos
        LEFT JOIN orders on ship_tos.order_id = orders.id
        LEFT JOIN user ON orders.user_id = user.user_id
        LEFT JOIN contacts on orders.contact_id = contacts.id
        $join_order_items
        $join_billing_states
        $join_billing_countries
        $join_shipping_states
        $join_shipping_countries
        $where
        $sql_status
        $sql_type
        ORDER BY ship_tos.order_id, ship_tos.id";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // assume that there is not a ship to until we find one
    $ship_to_exists = false;

    while ($row = mysqli_fetch_assoc($result)) {
        // for each value in the row
        foreach ($row as $key => $value) {
           // replace quotation mark with two quotation marks
           $value = str_replace('"', '""', $value);
           // set new value
           $row[$key] = $value;
        }
        
        // if this shipping address is verified, then convert salutation and country to all uppercase
        if ($row['address_verified'] == 1) {
            $row['salutation'] = mb_strtoupper($row['salutation']);
            $row['country'] = mb_strtoupper($row['country']);
        }

        // prepare row for ship_tos.csv data
        $ship_to_data .=
            '"' . $row['id'] . '",' .
            '"' . $row['order_id'] . '",' .
            '"' . $row['order_number'] . '",' .
            '"' . $row['ship_to_name'] . '",' .
            '"' . $row['salutation'] . '",' .
            '"' . $row['first_name'] . '",' .
            '"' . $row['last_name'] . '",' .
            '"' . $row['company'] . '",' .
            '"' . $row['address_1'] . '",' .
            '"' . $row['address_2'] . '",' .
            '"' . $row['city'] . '",' .
            '"' . $row['state'] . '",' .
            '"' . $row['zip_code'] . '",' .
            '"' . $row['country'] . '",' .
            '"' . $row['address_type'] . '",' .
            '"' . $row['arrival_date_code'] . '",' .
            '"' . prepare_form_data_for_output($row['arrival_date'], 'date') . '",' .
            '"' . prepare_form_data_for_output($row['ship_date'], 'date') . '",' .
            '"' . prepare_form_data_for_output($row['delivery_date'], 'date') . '",' .
            '"' . $row['shipping_method_code'] . '",' .
            '"' . sprintf("%01.2lf", $row['shipping_cost'] / 100) . '"';
        
        // loop through the custom field names in order to output custom shipping form data for this ship to row
        foreach ($custom_field_names as $custom_field_name) {
            $ship_to_data .= ',"' . str_replace('"', '""', $ship_tos[$row['id']][$custom_field_name]) . '"';
        }
        
        $ship_to_data .= "\n";

        $ship_to_exists = true;
    }

    $output_custom_field_1_heading = '';

    // If the first custom product field is active, then output heading for it.
    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
        $output_custom_field_1_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL) . '",';
    }

    $output_custom_field_2_heading = '';

    // If the second custom product field is active, then output heading for it.
    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
        $output_custom_field_2_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL) . '",';
    }

    $output_custom_field_3_heading = '';

    // If the third custom product field is active, then output heading for it.
    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
        $output_custom_field_3_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL) . '",';
    }

    $output_custom_field_4_heading = '';

    // If the fourth custom product field is active, then output heading for it.
    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
        $output_custom_field_4_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL) . '",';
    }

    // prepare first row of order_items.csv data
    $order_item_data =
        '"order_item_id",' .
        '"order_id",' .
        '"order_number",' .
        '"ship_to_id",' .
        '"product_name",' .
        '"quantity",' .
        '"shipped_quantity",' .
        '"price",' .
        '"tax",' .
        '"recurring_payment_period",' .
        '"recurring_number_of_payments",' .
        '"recurring_start_date",' .
        $output_custom_field_1_heading .
        $output_custom_field_2_heading .
        $output_custom_field_3_heading .
        $output_custom_field_4_heading .
        '"notes"' . "\n";

    // get order item data
    $query =
        "SELECT
            order_items.*,
            orders.order_number,
            products.custom_field_1,
            products.custom_field_2,
            products.custom_field_3,
            products.custom_field_4,
            products.notes
        FROM order_items
        LEFT JOIN orders on order_items.order_id = orders.id
        LEFT JOIN user ON orders.user_id = user.user_id
        LEFT JOIN contacts on orders.contact_id = contacts.id
        LEFT JOIN products on order_items.product_id = products.id
        $join_ship_tos
        $join_billing_states
        $join_billing_countries
        $join_shipping_states
        $join_shipping_countries
        $where
        $sql_status
        $sql_type
        ORDER BY order_items.order_id, order_items.id";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        // for each value in the row
        foreach ($row as $key => $value) {
           // replace quotation mark with two quotation marks
           $value = str_replace('"', '""', $value);
           // set new value
           $row[$key] = $value;
        }
        
        $shipped_quantity = '';
        
        // if shipped quantity should be shown then show it
        if ($row['show_shipped_quantity'] == 1) {
            $shipped_quantity = $row['shipped_quantity'];
        }
        
        $recurring_payment_period = '';
        $recurring_number_of_payments = '';
        $recurring_start_date = '';

        // if order item is a recurring order item, then prepare values
        if ($row['recurring_payment_period'] != '') {
            $recurring_payment_period = $row['recurring_payment_period'];
            $recurring_number_of_payments = $row['recurring_number_of_payments'];
            $recurring_start_date = prepare_form_data_for_output($row['recurring_start_date'], 'date');
        }

        $output_custom_field_1 = '';

        // If the first custom product field is active, then output value for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
            $output_custom_field_1 = '"' . escape_csv($row['custom_field_1']) . '",';
        }

        $output_custom_field_2 = '';

        // If the second custom product field is active, then output value for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
            $output_custom_field_2 = '"' . escape_csv($row['custom_field_2']) . '",';
        }

        $output_custom_field_3 = '';

        // If the third custom product field is active, then output value for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
            $output_custom_field_3 = '"' . escape_csv($row['custom_field_3']) . '",';
        }

        $output_custom_field_4 = '';

        // If the fourth custom product field is active, then output value for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
            $output_custom_field_4 = '"' . escape_csv($row['custom_field_4']) . '",';
        }

        // prepare row for order_items.csv data
        $order_item_data .=
            '"' . $row['id'] . '",' .
            '"' . $row['order_id'] . '",' .
            '"' . $row['order_number'] . '",' .
            '"' . $row['ship_to_id'] . '",' .
            '"' . $row['product_name'] . '",' .
            '"' . $row['quantity'] . '",' .
            '"' . $shipped_quantity . '",' .
            '"' . sprintf("%01.2lf", $row['price'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $row['tax'] / 100) . '",' .
            '"' . $recurring_payment_period . '",' .
            '"' . $recurring_number_of_payments . '",' .
            '"' . $recurring_start_date . '",' .
            $output_custom_field_1 .
            $output_custom_field_2 .
            $output_custom_field_3 .
            $output_custom_field_4 .
            '"' . $row['notes'] . '"' . "\n";
    }
    
    $shipping_tracking_number_data = '';

    // get shipping tracking numbers
    $query =
        "SELECT
            shipping_tracking_numbers.id,
            shipping_tracking_numbers.order_id,
            orders.order_number,
            shipping_tracking_numbers.ship_to_id,
            shipping_tracking_numbers.number
        FROM shipping_tracking_numbers
        LEFT JOIN orders on shipping_tracking_numbers.order_id = orders.id
        LEFT JOIN user ON orders.user_id = user.user_id
        LEFT JOIN contacts on orders.contact_id = contacts.id
        $join_order_items
        $join_ship_tos
        $join_billing_states
        $join_billing_countries
        $join_shipping_states
        $join_shipping_countries
        $where
        $sql_status
        $sql_type
        ORDER BY shipping_tracking_numbers.order_id, shipping_tracking_numbers.id";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $shipping_tracking_numbers = mysqli_fetch_items($result);
    
    // if there is at least one shipping tracking number, then prepare CSV data
    if (count($shipping_tracking_numbers) > 0) {
        // prepare first row of shipping_tracking_numbers.csv data
        $shipping_tracking_number_data .=
            '"shipping_tracking_number_id",' .
            '"order_id",' .
            '"order_number",' .
            '"ship_to_id",' .
            '"number"' . "\n";
            
        // loop through the shipping tracking numbers in order to prepare CSV data
        foreach ($shipping_tracking_numbers as $shipping_tracking_number) {
            // prepare row for shipping_tracking_numbers.csv data
            $shipping_tracking_number_data .=
                '"' . $shipping_tracking_number['id'] . '",' .
                '"' . $shipping_tracking_number['order_id'] . '",' .
                '"' . $shipping_tracking_number['order_number'] . '",' .
                '"' . $shipping_tracking_number['ship_to_id'] . '",' .
                '"' . str_replace('"', '""', $shipping_tracking_number['number']) . '"' . "\n";
        }
    }
    
    $zipfile = new zipfile();

    $zipfile->add_file($order_data, 'orders.csv');

    // if at least one ship to exists, then include ship tos file
    if ($ship_to_exists == true) {
        $zipfile->add_file($ship_to_data, 'ship_tos.csv');
    }

    $zipfile->add_file($order_item_data, 'order_items.csv');
    
    // if there is at least one shipping tracking number, then include file for it
    if ($shipping_tracking_number_data != '') {
        $zipfile->add_file($shipping_tracking_number_data, 'shipping_tracking_numbers.csv');
    }

    header("Content-type: application/octet-stream");
    header("Content-disposition: attachment; filename=orders.zip");
    print $zipfile->file();

} else if (($_GET['submit_data'] ?? '') == 'Export Orders (single file)') {
    // get orders
    $query =
        "SELECT
            orders.id,
            orders.order_number,
            orders.custom_field_1,
            orders.custom_field_2,
            orders.billing_salutation,
            orders.billing_first_name,
            orders.billing_last_name,
            orders.billing_email_address,
            orders.billing_company,
            orders.billing_address_1,
            orders.billing_address_2,
            orders.billing_city,
            orders.billing_state,
            orders.billing_zip_code,
            orders.billing_country,
            orders.billing_phone_number,
            orders.billing_fax_number,
            orders.payment_method,
            orders.card_type,
            orders.cardholder,
            orders.card_number,
            orders.expiration_month,
            orders.expiration_year,
            orders.card_verification_number,
            orders.referral_source_code,
            orders.po_number,
            orders.tax_exempt,
            orders.opt_in,
            orders.subtotal,
            orders.discount,
            orders.tax,
            orders.shipping,
            orders.gift_card_discount,
            orders.surcharge,
            orders.total,
            orders.commission,
            orders.order_date,
            orders.transaction_id,
            orders.authorization_code,
            orders.special_offer_code,
            orders.status,
            orders.reference_code,
            orders.tracking_code,
            orders.utm_source,
            orders.utm_medium,
            orders.utm_campaign,
            orders.utm_term,
            orders.utm_content,
            orders.affiliate_code,
            orders.currency_code,
            orders.http_referer,
            INET_NTOA(ip_address) as ip_address,
            contacts.member_id
        FROM orders
        LEFT JOIN user ON orders.user_id = user.user_id
        LEFT JOIN contacts on orders.contact_id = contacts.id
        $join_order_items
        $join_ship_tos
        $join_billing_states
        $join_billing_countries
        $join_shipping_states
        $join_shipping_countries
        $where
        $sql_status
        $sql_type
        ORDER BY orders.order_number";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $orders = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $orders[$row['id']] = $row;
        $orders[$row['id']]['order_items'] = array();
        $orders[$row['id']]['ship_tos'] = array();
        $orders[$row['id']]['custom_fields'] = array();
    }

    // get ship tos
    $query =
        "SELECT
            ship_tos.id,
            ship_tos.order_id,
            ship_tos.ship_to_name,
            ship_tos.salutation,
            ship_tos.first_name,
            ship_tos.last_name,
            ship_tos.company,
            ship_tos.address_1,
            ship_tos.address_2,
            ship_tos.city,
            ship_tos.state,
            ship_tos.zip_code,
            ship_tos.country,
            ship_tos.address_type,
            ship_tos.address_verified,
            ship_tos.arrival_date_code,
            ship_tos.arrival_date,
            ship_tos.ship_date,
            ship_tos.delivery_date,
            ship_tos.shipping_method_code,
            ship_tos.shipping_cost
        FROM ship_tos
        LEFT JOIN orders on ship_tos.order_id = orders.id
        LEFT JOIN user ON orders.user_id = user.user_id
        LEFT JOIN contacts on orders.contact_id = contacts.id
        $join_order_items
        $join_billing_states
        $join_billing_countries
        $join_shipping_states
        $join_shipping_countries
        $where
        $sql_status
        $sql_type
        ORDER BY ship_tos.id";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // if a ship to exists, add ship tos to orders array
    if (mysqli_num_rows($result) > 0) {
        $ship_tos_exist = true;

        while ($row = mysqli_fetch_assoc($result)) {
            $orders[$row['order_id']]['ship_tos'][$row['id']] = $row;
            $orders[$row['order_id']]['ship_tos'][$row['id']]['order_items'] = array();
            $orders[$row['order_id']]['ship_tos'][$row['id']]['custom_fields'] = array();
            $orders[$row['order_id']]['ship_tos'][$row['id']]['shipping_tracking_numbers'] = array();
        }

    // else a ship to does not exist
    } else {
        $ship_tos_exist = false;
    }

    // get order items
    $query =
        "SELECT
            order_items.id,
            order_items.order_id,
            order_items.ship_to_id,
            order_items.product_name,
            products.short_description,
            order_items.quantity,
            order_items.price,
            order_items.tax,
            order_items.recurring_payment_period,
            order_items.recurring_number_of_payments,
            order_items.recurring_start_date,
            order_items.show_shipped_quantity,
            order_items.shipped_quantity,
            products.custom_field_1,
            products.custom_field_2,
            products.custom_field_3,
            products.custom_field_4,
            products.notes
        FROM order_items
        LEFT JOIN orders on order_items.order_id = orders.id
        LEFT JOIN user ON orders.user_id = user.user_id
        LEFT JOIN contacts on orders.contact_id = contacts.id
        LEFT JOIN products on order_items.product_id = products.id
        $join_ship_tos
        $join_billing_states
        $join_billing_countries
        $join_shipping_states
        $join_shipping_countries
        $where
        $sql_status
        $sql_type
        ORDER BY order_items.id";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        // if order item does not have a ship to, then add order item to orders array
        if ($row['ship_to_id'] == 0) {
            $orders[$row['order_id']]['order_items'][$row['id']] = $row;

        // else order item does have a ship to, so add order item to ship tos array in orders array
        } else {
            $orders[$row['order_id']]['ship_tos'][$row['ship_to_id']]['order_items'][$row['id']] = $row;
        }
    }
    
    // get shipping tracking numbers
    $query =
        "SELECT
            shipping_tracking_numbers.id,
            shipping_tracking_numbers.order_id,
            shipping_tracking_numbers.ship_to_id,
            shipping_tracking_numbers.number
        FROM shipping_tracking_numbers
        LEFT JOIN orders on shipping_tracking_numbers.order_id = orders.id
        LEFT JOIN user ON orders.user_id = user.user_id
        LEFT JOIN contacts on orders.contact_id = contacts.id
        $join_order_items
        $join_ship_tos
        $join_billing_states
        $join_billing_countries
        $join_shipping_states
        $join_shipping_countries
        $where
        $sql_status
        $sql_type
        ORDER BY shipping_tracking_numbers.id ASC";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
         $orders[$row['order_id']]['ship_tos'][$row['ship_to_id']]['shipping_tracking_numbers'][$row['id']] = $row;
    }

    // if multi currency is enabled
    if (ECOMMERCE_MULTICURRENCY === true) {
        $multi_currency_column_name = '"currency_code",';
    } else {
        $multi_currency_column_name = '';
    }

    // Initialize array that will be used for storing custom field names for custom billing form data.
    $custom_billing_field_names = array();

    // Get all custom billing form data in database.
    $fields = db_items(
        "SELECT
            order_id,
            data,
            name
        FROM form_data
        WHERE
            (order_id != '0')
            AND (ship_to_id = '0')
            AND (order_item_id = '0')
        ORDER BY id ASC");

    // Loop through the fields in order to determine custom field names
    // and add fields to orders array for custom billing form data.
    foreach ($fields as $field) {
        // If this field is for an order that will appear in this export then deal with this field.
        if (isset($orders[$field['order_id']]) == true) {
            // If this field name has not been added to the custom field names array, then add it,
            // so we can keep track of all necessary field names
            if (in_array($field['name'], $custom_billing_field_names) == false) {
                $custom_billing_field_names[] = $field['name'];
            }

            // If there is not already form data for this field, then just add data to the array.
            if ((isset($orders[$field['order_id']]['custom_fields'][$field['name']]) == false) || ($orders[$field['order_id']]['custom_fields'][$field['name']] == '')) {
                $orders[$field['order_id']]['custom_fields'][$field['name']] = $field['data'];
                
            // Otherwise there is already form data for this field, so this is probably a field that supports multiple values,
            // so just append this additional value.
            } else {
                $orders[$field['order_id']]['custom_fields'][$field['name']] .= ', ' . $field['data'];
            }
        }
    }

    // initialize download dialog
    header("Content-type: text/csv");
    header("Content-disposition: attachment; filename=orders.csv");

    // prepare column headings for order records
    echo
        '"order_number",' .
        '"custom_field_1",' .
        '"custom_field_2",' .
        '"billing_salutation",' .
        '"billing_first_name",' .
        '"billing_last_name",' .
        '"billing_email_address",' .
        '"billing_company",' .
        '"billing_address_1",' .
        '"billing_address_2",' .
        '"billing_city",' .
        '"billing_state",' .
        '"billing_zip_code",' .
        '"billing_country",' .
        '"billing_phone_number",' .
        '"billing_fax_number",' .
        '"payment_method",' .
        '"card_type",' .
        '"cardholder",' .
        '"card_number",' .
        '"expiration_month",' .
        '"expiration_year",' .
        '"card_verification_number",' .
        '"referral_source_code",' .
        '"po_number",' .
        '"tax_exempt",' .
        '"opt_in",' .
        '"subtotal",' .
        '"discount",' .
        '"tax",' .
        '"shipping",' .
        '"gift_card_discount",' .
        '"surcharge",' .
        '"total",' .
        '"commission",' .
        '"order_date",' .
        '"transaction_id",' .
        '"authorization_code",' .
        '"special_offer_code",' .
        '"reference_code",' .
        '"tracking_code",' .
        '"utm_source",' .
        '"utm_medium",' .
        '"utm_campaign",' .
        '"utm_term",' .
        '"utm_content",' .
        '"member_id",' .
        '"affiliate_code",' .
        $multi_currency_column_name .
        '"http_referer",' . 
        '"ip_address"';
        
    // Loop through the custom billing field names in order to output them in the order heading row.
    foreach ($custom_billing_field_names as $custom_billing_field_name) {
        echo ',"' . str_replace('"', '""', $custom_billing_field_name) . '"';
    }
    
    echo "\n";

    // if ship tos exist
    if ($ship_tos_exist == true) {
        // get all custom shipping form data in database
        $query =
            "SELECT
                order_id,
                ship_to_id,
                data,
                name
            FROM form_data
            WHERE ship_to_id != '0'
            ORDER BY id ASC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // initialize array that will be used for storing custom field names for custom shipping form data
        $custom_field_names = array();
        
        // loop through the fields in order to determine custom field names and add fields to orders array for custom shipping form data
        while ($field = mysqli_fetch_assoc($result)) {
            // if this field is for an order that will appear in this export then deal with this field
            if (isset($orders[$field['order_id']]) == TRUE) {
                // if this field name has not been added to the custom field names array, then add it,
                // so we can keep track of all necessary field names
                if (in_array($field['name'], $custom_field_names) == FALSE) {
                    $custom_field_names[] = $field['name'];
                }
                
                // if there is not already form data for this field in the orders array, then just add data to the array
                if ((isset($orders[$field['order_id']]['ship_tos'][$field['ship_to_id']]['custom_fields'][$field['name']]) == FALSE) || ($orders[$field['order_id']]['ship_tos'][$field['ship_to_id']]['custom_fields'][$field['name']] == '')) {
                    $orders[$field['order_id']]['ship_tos'][$field['ship_to_id']]['custom_fields'][$field['name']] = $field['data'];
                    
                // else there is already form data for this field, so this is probably a field that supports multiple values,
                // so just append this additional value
                } else {
                    $orders[$field['order_id']]['ship_tos'][$field['ship_to_id']]['custom_fields'][$field['name']] .= ', ' . $field['data'];
                }
            }
        }
        
        $ship_to_indentation = '"",';
        $order_item_indentation = '"","",';
        $submitted_product_form_indentation = '"","","",';

        // prepare column headings for ship to records
        print
            $ship_to_indentation .
            '"ship_to_name",' .
            '"salutation",' .
            '"first_name",' .
            '"last_name",' .
            '"company",' .
            '"address_1",' .
            '"address_2",' .
            '"city",' .
            '"state",' .
            '"zip_code",' .
            '"country",' .
            '"address_type",' .
            '"arrival_date_code",' .
            '"arrival_date",' .
            '"ship_date",' .
            '"delivery_date",' .
            '"shipping_method_code",' .
            '"shipping_tracking_numbers",' .
            '"shipping_cost"';
        
        // loop through the custom field names in order to output them in the ship to heading row
        foreach ($custom_field_names as $custom_field_name) {
            print ',"' . str_replace('"', '""', $custom_field_name) . '"';
        }
        
        print "\n";
        
    } else {
        $order_item_indentation = '"",';
        $submitted_product_form_indentation = '"","",';
    }

    $output_custom_field_1_heading = '';

    // If the first custom product field is active, then output heading for it.
    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
        $output_custom_field_1_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL) . '",';
    }

    $output_custom_field_2_heading = '';

    // If the second custom product field is active, then output heading for it.
    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
        $output_custom_field_2_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL) . '",';
    }

    $output_custom_field_3_heading = '';

    // If the third custom product field is active, then output heading for it.
    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
        $output_custom_field_3_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL) . '",';
    }

    $output_custom_field_4_heading = '';

    // If the fourth custom product field is active, then output heading for it.
    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
        $output_custom_field_4_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL) . '",';
    }

    // prepare column headings for order item records
    print
        $order_item_indentation .
        '"product_name",' .
        '"product_description",' .
        '"quantity",' .
        '"shipped_quantity",' .
        '"price",' .
        '"tax",' .
        '"recurring_payment_period",' .
        '"recurring_number_of_payments",' .
        '"recurring_start_date",' .
        $output_custom_field_1_heading .
        $output_custom_field_2_heading .
        $output_custom_field_3_heading .
        $output_custom_field_4_heading .
        '"notes"' . "\n";

    // loop through orders in order to prepare data
    foreach ($orders as $order) {
        // loop through all values for this order in order to replace quotation marks with two quotation marks
        foreach ($order as $key => $value) {
            // if value is not the order items or ship tos array, replace quotation mark with two quotation marks
            if (($key != 'order_items') && ($key != 'ship_tos')) {
               $value = str_replace('"', '""', $value);
               $order[$key] = $value;
            }
        }
        
        $card_number = $order['card_number'];
        
        // if the credit card number is encrypted
        if ((mb_substr($card_number, 0, 1) != '*') && (mb_strlen($card_number) > 16)) {
            // if encryption is enabled, then decrypt the credit card number
            if (
                (defined('ENCRYPTION_KEY') == TRUE)
                && (extension_loaded('mcrypt') == TRUE)
                && (in_array('rijndael-256', mcrypt_list_algorithms()) == TRUE)
            ) {
                $card_number = decrypt_credit_card_number($card_number, ENCRYPTION_KEY);
                
                // if the credit card number is not numeric, then there was a decryption error
                if (is_numeric($card_number) == FALSE) {
                    $card_number = '[decryption error]';

                // else the credit card number was decrypted successfully,
                // so if the user does not have access to view card data,
                // then protect the credit card number
                } else if (($user['role'] == 3) && ($user['view_card_data'] == FALSE)) {
                    $card_number = protect_credit_card_number($card_number);
                }
                
            // else encryption is disabled, so output error
            } else {
                $card_number = '[decryption error]';
            }
        }
        
        $card_verification_number = $order['card_verification_number'];
        
        // if the card verification number is not already protected,
        // and the user does not have access to view card data,
        // then protect it
        if (
            (mb_substr($card_verification_number, 0, 1) != '*')
            && (($user['role'] == 3) && ($user['view_card_data'] == FALSE))
        ) {
            $card_verification_number = protect_card_verification_number($card_verification_number);
        }

        if ($order['tax_exempt']) {
            $tax_exempt = 'yes';
        } else {
            $tax_exempt = 'no';
        }

        if ($order['opt_in']) {
            $opt_in = 'yes';
        } else {
            $opt_in = 'no';
        }

        // If the date format is month and then day, then use that format.
        if (DATE_FORMAT == 'month_day') {
            $month_and_day_format = 'n/j';

        // Otherwise the date format is day and then month, so use that format.
        } else {
            $month_and_day_format = 'j/n';
        }

        // if multi currency is enabled
        if (ECOMMERCE_MULTICURRENCY === true) {
            $currency_code = '"' . $order['currency_code'] . '",';
        } else {
            $currency_code = '';
        }
        
        $ip_address = $order['ip_address'];
        
        // if the IP address is 0.0.0.0, then we don't know the IP address, so set the value to empty string
        if ($ip_address == '0.0.0.0') {
            $ip_address = '';
        }

        // prepare order row
        echo
            '"' . $order['order_number'] . '",' .
            '"' . $order['custom_field_1'] . '",' .
            '"' . $order['custom_field_2'] . '",' .
            '"' . $order['billing_salutation'] . '",' .
            '"' . $order['billing_first_name'] . '",' .
            '"' . $order['billing_last_name'] . '",' .
            '"' . $order['billing_email_address'] . '",' .
            '"' . $order['billing_company'] . '",' .
            '"' . $order['billing_address_1'] . '",' .
            '"' . $order['billing_address_2'] . '",' .
            '"' . $order['billing_city'] . '",' .
            '"' . $order['billing_state'] . '",' .
            '"' . $order['billing_zip_code'] . '",' .
            '"' . $order['billing_country'] . '",' .
            '"' . $order['billing_phone_number'] . '",' .
            '"' . $order['billing_fax_number'] . '",' .
            '"' . $order['payment_method'] . '",' .
            '"' . $order['card_type'] . '",' .
            '"' . $order['cardholder'] . '",' .
            '"#' . $card_number . '",' .
            '"' . $order['expiration_month'] . '",' .
            '"' . $order['expiration_year'] . '",' .
            '"' . $card_verification_number . '",' .
            '"' . $order['referral_source_code'] . '",' .
            '"' . $order['po_number'] . '",' .
            '"' . $tax_exempt . '",' .
            '"' . $opt_in . '",' .
            '"' . sprintf("%01.2lf", $order['subtotal'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['discount'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['tax'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['shipping'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['gift_card_discount'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['surcharge'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['total'] / 100) . '",' .
            '"' . sprintf("%01.2lf", $order['commission'] / 100) . '",' .
            '"' . date($month_and_day_format . '/Y g:i:s A T', $order['order_date']) . '",' .
            '"' . $order['transaction_id'] . '",' .
            '"' . $order['authorization_code'] . '",' .
            '"' . $order['special_offer_code'] . '",' .
            '"' . $order['reference_code'] . '",' .
            '"' . $order['tracking_code'] . '",' .
            '"' . $order['utm_source'] . '",' .
            '"' . $order['utm_medium'] . '",' .
            '"' . $order['utm_campaign'] . '",' .
            '"' . $order['utm_term'] . '",' .
            '"' . $order['utm_content'] . '",' .
            '"' . $order['member_id'] . '",' .
            '"' . $order['affiliate_code'] . '",' .
            $currency_code .
            '"' . $order['http_referer'] . '",' .
            '"' . $ip_address . '"';
        
        // Loop through the custom billing field names in order to output custom billing form data for this order row.
        foreach ($custom_billing_field_names as $custom_billing_field_name) {
            echo ',"' . str_replace('"', '""', $order['custom_fields'][$custom_billing_field_name]) . '"';
        }
        
        echo "\n";

        // loop through order items for this order in order to prepare data
        foreach ($order['order_items'] as $order_item) {
            // for each value in the row
            foreach ($order_item as $key => $value) {
               // replace quotation mark with two quotation marks
               $value = str_replace('"', '""', $value);
               // set new value
               $order_item[$key] = $value;
            }
            
            $shipped_quantity = '';
            
            // if shipped quantity should be shown then show it
            if ($order_item['show_shipped_quantity'] == 1) {
                $shipped_quantity = $order_item['shipped_quantity'];
            }

            $recurring_payment_period = '';
            $recurring_number_of_payments = '';
            $recurring_start_date = '';

            // if order item is a recurring order item, then prepare values
            if ($order_item['recurring_payment_period'] != '') {
                $recurring_payment_period = $order_item['recurring_payment_period'];
                $recurring_number_of_payments = $order_item['recurring_number_of_payments'];
                $recurring_start_date = prepare_form_data_for_output($order_item['recurring_start_date'], 'date');
            }

            $output_custom_field_1 = '';

            // If the first custom product field is active, then output value for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
                $output_custom_field_1 = '"' . escape_csv($order_item['custom_field_1']) . '",';
            }

            $output_custom_field_2 = '';

            // If the second custom product field is active, then output value for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
                $output_custom_field_2 = '"' . escape_csv($order_item['custom_field_2']) . '",';
            }

            $output_custom_field_3 = '';

            // If the third custom product field is active, then output value for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
                $output_custom_field_3 = '"' . escape_csv($order_item['custom_field_3']) . '",';
            }

            $output_custom_field_4 = '';

            // If the fourth custom product field is active, then output value for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
                $output_custom_field_4 = '"' . escape_csv($order_item['custom_field_4']) . '",';
            }

            // prepare order item row
            print
                $order_item_indentation .
                '"' . $order_item['product_name'] . '",' .
                '"' . $order_item['short_description'] . '",' .
                '"' . $order_item['quantity'] . '",' .
                '"' . $shipped_quantity . '",' .
                '"' . sprintf("%01.2lf", $order_item['price'] / 100) . '",' .
                '"' . sprintf("%01.2lf", $order_item['tax'] / 100) . '",' .
                '"' . $recurring_payment_period . '",' .
                '"' . $recurring_number_of_payments . '",' .
                '"' . $recurring_start_date . '",' .
                $output_custom_field_1 .
                $output_custom_field_2 .
                $output_custom_field_3 .
                $output_custom_field_4 .
                '"' . $order_item['notes'] . '"' . "\n";

            // get maximum quantity number, so we can determine how many product forms there are for this order item
            $query = "SELECT MAX(quantity_number) as number_of_forms FROM form_data WHERE order_item_id = '" . $order_item['id'] . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $number_of_forms = $row['number_of_forms'];

            // if there is a form for this order item, then prepare to output form
            if ($number_of_forms > 0) {
                $heading_row = $submitted_product_form_indentation;
                $data_rows = '';

                // create loop in order to output all forms
                for ($quantity_number = 1; $quantity_number <= $number_of_forms; $quantity_number++) {
                    // get form data items for order item
                    $query =
                        "SELECT
                            form_field_id,
                            data,
                            name,
                            count(*) as number_of_values
                        FROM form_data
                        WHERE
                            (order_item_id = '" . $order_item['id'] . "')
                            AND (quantity_number = '$quantity_number')
                        GROUP BY form_field_id
                        ORDER BY id";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    $form_data_items = array();

                    // loop through form data items in order to add them to array
                    while ($row = mysqli_fetch_assoc($result)) {
                        $form_data_items[] = $row;
                    }

                    // loop through form data items in order to prepare rows of data
                    foreach ($form_data_items as $key => $form_data_item) {
                        // if this is the first product form, then prepare heading row
                        if ($quantity_number == 1) {
                            // if this is not the first form data item, then add a comma to the heading
                            if ($key != 0) {
                                $heading_row .= ',';
                            }

                            $heading_row .= '"' . str_replace('"', '""', $form_data_item['name']) . '"';

                            // if this is the last data item, then add a new line
                            if ($key == (count($form_data_items) - 1)) {
                                $heading_row .= "\n";
                            }
                        }

                        // if this is the first form data item, then add indentation
                        if ($key == 0) {
                            $data_rows .= $submitted_product_form_indentation;

                        // else this is not the first form data item, so add a comma
                        } else {
                            $data_rows .= ',';
                        }

                        $data = '';

                        // if there is more than one value, then get all values so data can be set to all values
                        if ($form_data_item['number_of_values'] > 1) {
                            $query =
                                "SELECT data
                                FROM form_data
                                WHERE
                                    (order_item_id = '" . $order_item['id'] . "')
                                    AND (quantity_number = '$quantity_number')
                                    AND (form_field_id = '" . $form_data_item['form_field_id'] . "')
                                ORDER BY id";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                            while ($row = mysqli_fetch_assoc($result)) {
                                // if data is not empty, then add comma and space
                                if ($data != '') {
                                    $data .= ', ';
                                }

                                $data .= $row['data'];
                            }

                        // else there is just one value, so set data
                        } else {
                            $data = $form_data_item['data'];
                        }

                        $data_rows .= '"' . str_replace('"', '""', $data) . '"';

                        // if this is the last data item, then add a new line
                        if ($key == (count($form_data_items) - 1)) {
                            $data_rows .= "\n";
                        }
                    }
                }

                print $heading_row;
                print $data_rows;
            }
        }

        // loop through ship tos for this order in order to prepare data
        foreach ($order['ship_tos'] as $ship_to) {
            // loop through all values for this ship to in order to replace quotation marks with two quotation marks
            foreach ($ship_to as $key => $value) {
                // if value is not the order items and shipping_tracking_numbers arrays, replace quotation mark with two quotation marks
                if (($key != 'order_items') && ($key != 'shipping_tracking_numbers')) {
                   $value = str_replace('"', '""', $value);
                   $ship_to[$key] = $value;
                }
            }
            
            // if this shipping address is verified, then convert salutation and country to all uppercase
            if ($ship_to['address_verified'] == 1) {
                $ship_to['salutation'] = mb_strtoupper($ship_to['salutation']);
                $ship_to['country'] = mb_strtoupper($ship_to['country']);
            }
            
            $shipping_tracking_number_list = '';

            if (is_array($ship_to['shipping_tracking_numbers']) == true) {
                foreach ($ship_to['shipping_tracking_numbers'] as $shipping_tracking_number) {
                    // if this is not the first shipping tracking number then add a comma and space for separation
                    if ($shipping_tracking_number_list != '') {
                        $shipping_tracking_number_list .= ', ';
                    }
                    
                    $shipping_tracking_number_list .= str_replace('"', '""', $shipping_tracking_number['number']);
                }
            }

            // prepare ship to row
            print
                $ship_to_indentation .
                '"' . $ship_to['ship_to_name'] . '",' .
                '"' . $ship_to['salutation'] . '",' .
                '"' . $ship_to['first_name'] . '",' .
                '"' . $ship_to['last_name'] . '",' .
                '"' . $ship_to['company'] . '",' .
                '"' . $ship_to['address_1'] . '",' .
                '"' . $ship_to['address_2'] . '",' .
                '"' . $ship_to['city'] . '",' .
                '"' . $ship_to['state'] . '",' .
                '"' . $ship_to['zip_code'] . '",' .
                '"' . $ship_to['country'] . '",' .
                '"' . $ship_to['address_type'] . '",' .
                '"' . $ship_to['arrival_date_code'] . '",' .
                '"' . prepare_form_data_for_output($ship_to['arrival_date'], 'date') . '",' .
                '"' . prepare_form_data_for_output($ship_to['ship_date'], 'date') . '",' .
                '"' . prepare_form_data_for_output($ship_to['delivery_date'], 'date') . '",' .
                '"' . $ship_to['shipping_method_code'] . '",' .
                '"' . $shipping_tracking_number_list . '",' .
                '"' . sprintf("%01.2lf", $ship_to['shipping_cost'] / 100) . '"';
            
            // loop through the custom field names in order to output custom shipping form data for this ship to row
            foreach ($custom_field_names as $custom_field_name) {
                print ',"' . str_replace('"', '""', $ship_to['custom_fields'][$custom_field_name]) . '"';
            }
            
            print "\n";

            // loop through order items for this ship to in order to prepare data
            foreach ($ship_to['order_items'] as $order_item) {
                // for each value in the row
                foreach ($order_item as $key => $value) {
                   // replace quotation mark with two quotation marks
                   $value = str_replace('"', '""', $value);
                   // set new value
                   $order_item[$key] = $value;
                }
                
                $shipped_quantity = '';
                
                // if shipped quantity should be shown then show it
                if ($order_item['show_shipped_quantity'] == 1) {
                    $shipped_quantity = $order_item['shipped_quantity'];
                }

                $recurring_payment_period = '';
                $recurring_number_of_payments = '';
                $recurring_start_date = '';

                // if order item is a recurring order item, then prepare values
                if ($order_item['recurring_payment_period'] != '') {
                    $recurring_payment_period = $order_item['recurring_payment_period'];
                    $recurring_number_of_payments = $order_item['recurring_number_of_payments'];
                    $recurring_start_date = prepare_form_data_for_output($order_item['recurring_start_date'], 'date');
                }

                $output_custom_field_1 = '';

                // If the first custom product field is active, then output value for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
                    $output_custom_field_1 = '"' . escape_csv($order_item['custom_field_1']) . '",';
                }

                $output_custom_field_2 = '';

                // If the second custom product field is active, then output value for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
                    $output_custom_field_2 = '"' . escape_csv($order_item['custom_field_2']) . '",';
                }

                $output_custom_field_3 = '';

                // If the third custom product field is active, then output value for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
                    $output_custom_field_3 = '"' . escape_csv($order_item['custom_field_3']) . '",';
                }

                $output_custom_field_4 = '';

                // If the fourth custom product field is active, then output value for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
                    $output_custom_field_4 = '"' . escape_csv($order_item['custom_field_4']) . '",';
                }

                // prepare order item row
                print
                    $order_item_indentation .
                    '"' . $order_item['product_name'] . '",' .
                    '"' . $order_item['short_description'] . '",' .
                    '"' . $order_item['quantity'] . '",' .
                    '"' . $shipped_quantity . '",' .
                    '"' . sprintf("%01.2lf", $order_item['price'] / 100) . '",' .
                    '"' . sprintf("%01.2lf", $order_item['tax'] / 100) . '",' .
                    '"' . $recurring_payment_period . '",' .
                    '"' . $recurring_number_of_payments . '",' .
                    '"' . $recurring_start_date . '",' .
                    $output_custom_field_1 .
                    $output_custom_field_2 .
                    $output_custom_field_3 .
                    $output_custom_field_4 .
                    '"' . $order_item['notes'] . '"' . "\n";

                // get maximum quantity number, so we can determine how many product forms there are for this order item
                $query = "SELECT MAX(quantity_number) as number_of_forms FROM form_data WHERE order_item_id = '" . $order_item['id'] . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $number_of_forms = $row['number_of_forms'];

                // if there is a form for this order item, then prepare to output form
                if ($number_of_forms > 0) {
                    $heading_row = $submitted_product_form_indentation;
                    $data_rows = '';

                    // create loop in order to output all forms
                    for ($quantity_number = 1; $quantity_number <= $number_of_forms; $quantity_number++) {
                        // get form data items for order item
                        $query =
                            "SELECT
                                form_field_id,
                                data,
                                name,
                                count(*) as number_of_values
                            FROM form_data
                            WHERE
                                (order_item_id = '" . $order_item['id'] . "')
                                AND (quantity_number = '$quantity_number')
                            GROUP BY form_field_id
                            ORDER BY id";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                        $form_data_items = array();

                        // loop through form data items in order to add them to array
                        while ($row = mysqli_fetch_assoc($result)) {
                            $form_data_items[] = $row;
                        }

                        // loop through form data items in order to prepare rows of data
                        foreach ($form_data_items as $key => $form_data_item) {
                            // if this is the first product form, then prepare heading row
                            if ($quantity_number == 1) {
                                // if this is not the first form data item, then add a comma to the heading
                                if ($key != 0) {
                                    $heading_row .= ',';
                                }

                                $heading_row .= '"' . str_replace('"', '""', $form_data_item['name']) . '"';

                                // if this is the last data item, then add a new line
                                if ($key == (count($form_data_items) - 1)) {
                                    $heading_row .= "\n";
                                }
                            }

                            // if this is the first form data item, then add indentation
                            if ($key == 0) {
                                $data_rows .= $submitted_product_form_indentation;

                            // else this is not the first form data item, so add a comma
                            } else {
                                $data_rows .= ',';
                            }

                            $data = '';

                            // if there is more than one value, then get all values so data can be set to all values
                            if ($form_data_item['number_of_values'] > 1) {
                                $query =
                                    "SELECT data
                                    FROM form_data
                                    WHERE
                                        (order_item_id = '" . $order_item['id'] . "')
                                        AND (quantity_number = '$quantity_number')
                                        AND (form_field_id = '" . $form_data_item['form_field_id'] . "')
                                    ORDER BY id";
                                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                                while ($row = mysqli_fetch_assoc($result)) {
                                    // if data is not empty, then add comma and space
                                    if ($data != '') {
                                        $data .= ', ';
                                    }

                                    $data .= $row['data'];
                                }

                            // else there is just one value, so set data
                            } else {
                                $data = $form_data_item['data'];
                            }

                            $data_rows .= '"' . str_replace('"', '""', $data) . '"';

                            // if this is the last data item, then add a new line
                            if ($key == (count($form_data_items) - 1)) {
                                $data_rows .= "\n";
                            }
                        }
                    }

                    print $heading_row;
                    print $data_rows;
                }
            }
        }

        // If the status of this order is complete, then update the status to be exported.
        if ($order['status'] == 'complete') {
            db("UPDATE orders SET status = 'exported' WHERE id = '" . $order['id'] . "'");
        }
    }

// else user did not request to export orders, so view orders
} else {
    $statuses = array();

    $statuses[] = array('label' => '[' . lang('Any') . ']', 'value' => 'any');
    $statuses[] = array('label' => lang('Incomplete'), 'value' => 'incomplete');
    $statuses[] = array('label' => lang('Complete'), 'value' => 'complete');
    $statuses[] = array('label' => lang('Exported'), 'value' => 'exported');
    $statuses[] = array('label' => lang('Complete or Exported'), 'value' => 'complete_or_exported');
    $statuses[] = array('label' => lang('Cancelled'), 'value' => 'cancelled');

    if ($refund_columns_exist) {
        $statuses[] = array('label' => lang('Refund Pending'), 'value' => 'refund_pending');
    }

    $output_status_options = '';

    // Loop through the statuses in order to prepare pick list options.
    foreach ($statuses as $status) {
        $selected = '';

        // If this is the selected status, then select it.
        if ($status['value'] == ($_SESSION['software']['ecommerce']['view_orders']['status'] ?? '')) {
            $selected = ' selected="selected"';
        }

        $output_status_options .= '<option value="' . h($status['value']) . '"' . $selected . '>' . h($status['label']) . '</option>';
    }

    // Build type filter options (online / local / any).
    $types = array();
    $types[] = array('label' => '[' . lang('Any') . ']', 'value' => 'any');
    $types[] = array('label' => lang('Online'), 'value' => 'online');
    $types[] = array('label' => lang('Local'),  'value' => 'local');

    $output_type_options = '';
    foreach ($types as $type) {
        $selected = '';
        if ($type['value'] == ($_SESSION['software']['ecommerce']['view_orders']['type'] ?? '')) {
            $selected = ' selected="selected"';
        }
        $output_type_options .= '<option value="' . h($type['value']) . '"' . $selected . '>' . h($type['label']) . '</option>';
    }

    // get oldest timestamp
    $query = "SELECT MIN(order_date) FROM orders";
    $result = mysqli_query(db::$con, $query) or output_error("Query failed.");
    $row = mysqli_fetch_row($result);
    $oldest_timestamp = $row[0];

    // get minimum year from oldest timestamp
    $oldest_year = date('Y', $oldest_timestamp);
    if (($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? '') < $oldest_year) {
        $oldest_year = ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? '');
    }

    $this_year = date('Y', strtotime('+1 year'));
    if (($_SESSION['software']['ecommerce']['view_orders']['stop_year'] ?? '') > $this_year) {
        $this_year = ($_SESSION['software']['ecommerce']['view_orders']['stop_year'] ?? '');
    }

    $years = array();

    // create html for year options
    for ($i = $oldest_year; $i <= $this_year; $i++) {
        $years[] = $i;
    }

    // if sort was set, update session
    if (isset($_REQUEST['sort'])) {
        // store sort in session
        $_SESSION['software']['ecommerce']['view_orders']['sort'] = $_REQUEST['sort'];

        // clear order
        $_SESSION['software']['ecommerce']['view_orders']['order'] = '';
    }

    // if order was set, update session
    if (isset($_REQUEST['order'])) {
        $_SESSION['software']['ecommerce']['view_orders']['order'] = $_REQUEST['order'];
    }

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
    if (isset($_SESSION['software']['ecommerce']['view_orders']['sort']) == false) {
        $_SESSION['software']['ecommerce']['view_orders']['sort'] = '';
    }

    switch (($_SESSION['software']['ecommerce']['view_orders']['sort'] ?? '')) {
        case lang('First Name'):
            $sort_column = 'orders.billing_first_name';
            break;

        case lang('Last Name'):
            $sort_column = 'orders.billing_last_name';
            break;

        case lang('Status'):
            $sort_column = 'orders.status';
            break;

        case lang('Order Number'):
            $sort_column = 'orders.order_number';
            break;

        case lang('User'):
            $sort_column = 'user.user_username';
            break;

        case lang('Tracking Code'):
            $sort_column = 'orders.tracking_code';
            break;

        case MEMBER_ID_LABEL:
            $sort_column = 'contacts.member_id';
            break;

        case lang('Affiliate Code'):
            $sort_column = 'orders.affiliate_code';
            break;

        case lang('Total'):
            $sort_column = 'orders.total';
            break;

        case lang('Order Date'):
            $sort_column = 'orders.order_date';
            break;

        default:
            $sort_column = 'orders.order_date';
            $_SESSION['software']['ecommerce']['view_orders']['sort'] = 'Order Date';
    }

    if (!empty($_SESSION['software']['ecommerce']['view_orders']['order'])) {
        $asc_desc = ($_SESSION['software']['ecommerce']['view_orders']['order'] ?? '');
    } elseif ($sort_column == 'orders.order_date') {
        $asc_desc = 'desc';
        $_SESSION['software']['ecommerce']['view_orders']['order'] = 'desc';
    } else {
        $asc_desc = 'asc';
        $_SESSION['software']['ecommerce']['view_orders']['order'] = 'asc';
    }

    

    // Get all orders
    $query = "SELECT count(distinct(orders.id)) as count
             FROM orders";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $all_orders = $row['count'];

    // get total number of results for all screens, so that we can output links to different screens
    $query = "SELECT count(distinct(orders.id)) as count
             FROM orders
             LEFT JOIN user ON orders.user_id = user.user_id
             LEFT JOIN contacts on orders.contact_id = contacts.id
             $join_order_items
             $join_ship_tos
             $join_billing_states
             $join_billing_countries
             $join_shipping_states
             $join_shipping_countries
             $where
             $sql_status
             $sql_type";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $number_of_results = $row['count'];

  
    
    if (AFFILIATE_PROGRAM == true) {
        $output_affiliate_heading = '<th>' . get_column_heading(lang('Affiliate Code'), ($_SESSION['software']['ecommerce']['view_orders']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['order'] ?? '')) . '</th>';
    }


    /* get results for just this screen*/
    $query = "SELECT
                orders.id,
                orders.billing_first_name,
                orders.billing_last_name,
                orders.status,
                orders.parasut_exported,
                orders.order_number,
                user.user_username as username,
                orders.tracking_code,
                contacts.member_id,
                orders.affiliate_code,
                orders.card_number,
                orders.card_verification_number,
                orders.total,
                orders.order_date,
                orders.type
             FROM orders
             LEFT JOIN user ON orders.user_id = user.user_id
             LEFT JOIN contacts on orders.contact_id = contacts.id
             $join_order_items
             $join_ship_tos
             $join_billing_states
             $join_billing_countries
             $join_shipping_states
             $join_shipping_countries
             $where
             $sql_status
             $sql_type
             GROUP BY orders.id
             ORDER BY $sort_column $asc_desc";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $filter_totals = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $id = $row['id'];
        $billing_first_name = $row['billing_first_name'];
        $billing_last_name = $row['billing_last_name'];
        $status = $row['status'];
        $parasut_exported = $row['parasut_exported'];
        $order_number = $row['order_number'];
        $username = $row['username'];
        $tracking_code = $row['tracking_code'];
        $member_id = $row['member_id'];
        $affiliate_code = $row['affiliate_code'];
        $card_number = $row['card_number'];
        $order_type = $row['type'];
            
        $output_order_type = '';
        if($order_type == 'local'){
            $output_order_type = lang('Local');
        }else{
            $output_order_type = lang('Online');
            
        }
        $card_verification_number = $row['card_verification_number'];
        $total = $row['total'] / 100;
        $filter_totals = $filter_totals + $total;
        $order_date = get_relative_time(array('timestamp' => $row['order_date']));
        
        $output_link_url = 'view_order.php?id=' . $id . '&send_to=' . h(escape_javascript(PATH . SOFTWARE_DIRECTORY)) . '/view_orders.php';

        if (AFFILIATE_PROGRAM == true) {
            $output_affiliate_code = '<td class="align-middle">' . h($affiliate_code) . '</td>';
        } else {
            $output_affiliate_code = '';
        }
        
        // set checkmark image for columns to use
        $output_checkmark = '<span class="material-icons">task_alt</span>';

        $output_card_data_check_mark = '';
        
        // if there is a credit card number and the credit card number is not protected or there is a card verification number and it is not protected, then output check mark
        if (
            (($card_number != '') && (mb_substr($card_number, 0, 1) != '*'))
            || (($card_verification_number != '') && (mb_substr($card_verification_number, 0, 1) != '*'))
        ) {
            $output_card_data_check_mark = $output_checkmark;
        }

        $output_shipping_tracking_cell = '';

        // If shipping is enabled, then get shipping tracking numbers and output a icon for each one.
        if (ECOMMERCE_SHIPPING == true) {
            $output_shipping_tracking_icons = '';

            // If the order is complete, then continue to get shipping tracking info.
            if ($status != 'incomplete') {
                $shipping_tracking_numbers = db_values(
                    "SELECT number
                    FROM shipping_tracking_numbers
                    WHERE order_id = '" . $id . "'
                    ORDER BY
                        ship_to_id ASC,
                        id ASC");
                
                // Loop through the shipping tracking numbers in order to output icons for them.
                $i = 0;
                foreach ($shipping_tracking_numbers as $shipping_tracking_number) {
                        $i++;
                        $output_shipping_tracking_icons = '
                        <button type="button" class="btn-data-control m-1 btn btn-outline-success border-2 position-relative" title="' . lang('Shipment Tracking') . '" onclick="window.location.href=\'' . $output_link_url . '#trackin_number_from_view_orders\'">
                            <i class="bi bi-truck"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-white opacity-75 text-success">
                                ' . $i . '
                            </span>
                        </button>';
                }
            }
            
            $output_shipping_tracking_cell = $output_shipping_tracking_icons;
        }


        
        $output_status = '';
        if($status == 'incomplete'){
            $output_status = '<span class="badge bg-warning text-dark bg-gradient fw-light">' . lang(ucwords($status)) . '</span>';
        }else if($status == 'complete'){
            $output_status = '<span class="badge bg-primary bg-gradient fw-light">' . lang(ucwords($status)) . '</span>';
        }else if($status == 'exported'){
            $output_status = '<span class="badge bg-success bg-gradient fw-light">' . lang(ucwords($status)) . '</span>';
        }else if($status == 'canceled' || $status == 'cancelled'){
            $output_status = '<span class="badge bg-danger bg-gradient fw-light">' . lang('Cancelled') . '</span>';
        }
        if($parasut_exported != 0){
            $output_status = $output_status . '<span title="' . lang('This order has been exported for upload to parasut.com') . '" class="badge bg-transparent bg-gradient fw-light"><img width="20px" height="auto" src="assets/images/parasut-emblem.png"></span>';
        }
        



        // Per-row "İptal" button — visible when the order is not already
        // cancelled. The 2026.1.26 cancel feature lives on a separate POST
        // endpoint (cancel_order.php) rather than the bulk edit_orders.php
        // dispatch so the visitor-facing endpoint and the admin per-row
        // action share one code path.
        //
        // IMPORTANT: this MUST NOT be a <form>. Every row is rendered inside
        // the page-wide <form action="edit_orders.php">, and HTML forbids
        // nested forms — the parser silently DROPS the inner <form> start tag,
        // which (a) leaves the button submitting the OUTER form to
        // edit_orders.php with an empty action, (b) makes the row's hidden
        // inputs and a duplicate CSRF token leak into every bulk submit, and
        // (c) makes querySelectorAll('.pg-admin-cancel-form') match nothing so
        // the confirm handler never binds. That is exactly why the per-row
        // cancel button did nothing. Instead we emit a plain button carrying
        // the order id, and JS posts a single shared form that lives OUTSIDE
        // the page form (see #pg-admin-cancel-form near the bottom).
        $output_cancel_button = '';
        if ($status !== 'cancelled' && $status !== 'canceled' && $status !== 'incomplete') {
            $output_cancel_button =
                '<button type="button" class="btn-data-control m-1 btn btn-outline-danger border-2 pg-admin-cancel-btn no-submit" data-order-id="' . $id . '" data-order-number="' . h($order_number) . '" title="' . lang('Cancel Order') . '"><i class="bi bi-x-octagon"></i></button>';
        }

        $output_rows .=
            '<tr id="' . $id . '">
                <td class="select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="orders[]" value="' . $id . '" class="checkbox" /></td>
                <td class="align-middle text-start col-reorder-none action-buttons">
                    <button type="button" class="btn-data-control m-1 btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    ' . $output_cancel_button . '
                    ' . $output_shipping_tracking_cell . '
                </td>
                <td class="align-middle">' . h($billing_first_name) . '</td>
                <td class="align-middle">' . h($billing_last_name) . '</td>
                <td class="align-middle">' . $output_status . '</td>
                <td class="align-middle">' . $output_order_type . '</td>
                <td class="align-middle">' . $order_number . '</td>
                <td class="align-middle text-end">' . prepare_amount($total) . '</td>
                <td class="align-middle">' . h($username) . '</td>
                <td class="align-middle">' . h($tracking_code) . '</td>
                <td class="align-middle">' . h($member_id) . '</td>
                ' . $output_affiliate_code . '
                <td class="align-middle text-center">' . $output_card_data_check_mark . '</td>
                <td class="align-middle">' . $order_date . '</td>
            </tr>';
    }

    $filter_totals = prepare_amount($filter_totals);

    // if the advanced filters are off
    if (($_SESSION['software']['ecommerce']['view_orders']['advanced_filters'] ?? '') == false) {
      
        $output_advanced_filters_value = 'true';
        $output_advanced_filters_label = lang('Add Advanced Filters');
        $output_advanced_filters = '';
        $advanced_filters_icon = 'filter_list';
        $output_advanced_filters_class = 'btn-primary';

    // else the advanced filters are on
    } else {
   
        $output_advanced_filters_value = 'false';
        $output_advanced_filters_label = lang('Remove Advanced Filters');
        $advanced_filters_icon = 'filter_list_off';
        $output_advanced_filters_class = 'btn-danger';

        // prepare selection for payment method field
        if (($_SESSION['software']['ecommerce']['view_orders']['payment_method'] ?? '') == 'any') {
            $payment_method_any_selected = ' selected="selected"';
        } elseif ((isset($_SESSION['software']['ecommerce']['view_orders']['payment_method']) == true) && (($_SESSION['software']['ecommerce']['view_orders']['payment_method'] ?? '') == '')) {
            $payment_method_none_selected = ' selected="selected"';
        } elseif (($_SESSION['software']['ecommerce']['view_orders']['payment_method'] ?? '') == 'Credit/Debit Card') {
            $payment_method_credit_debit_card_selected = ' selected="selected"';
        } elseif (($_SESSION['software']['ecommerce']['view_orders']['payment_method'] ?? '') == 'PayPal Express Checkout') {
            $payment_method_paypal_express_checkout_selected = ' selected="selected"';
        } elseif (($_SESSION['software']['ecommerce']['view_orders']['payment_method'] ?? '') == 'Offline Payment') {
            $payment_method_offline_selected = ' selected="selected"';
        }

        if (AFFILIATE_PROGRAM == true) {
            $output_affiliate ='
                <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Affiliate') . '</h5></div>
                <div class="col-12">
                    <label class="form-label">' . lang('Affiliate Code') . '</label>
                    <input class="form-control" type="text" name="affiliate_code" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['affiliate_code'] ?? '')) . '" />
                </div>';
        }

        // prepare selection for opt-in status
        if (($_SESSION['software']['ecommerce']['view_orders']['opt_in_status'] ?? '') == 'any') {
            $opt_in_status_any_selected = ' selected="selected"';
        } elseif (($_SESSION['software']['ecommerce']['view_orders']['opt_in_status'] ?? '') == 'opt_in') {
            $opt_in_status_opt_in_selected = ' selected="selected"';
        } elseif (($_SESSION['software']['ecommerce']['view_orders']['opt_in_status'] ?? '') == 'opt_out') {
            $opt_in_status_opt_out_selected = ' selected="selected"';
        }

        // prepare selection for tax status
        if (($_SESSION['software']['ecommerce']['view_orders']['tax_status'] ?? '') == 'any') {
            $tax_status_any_selected = ' selected="selected"';
        } elseif (($_SESSION['software']['ecommerce']['view_orders']['tax_status'] ?? '') == 'tax_exempt') {
            $tax_status_tax_exempt_selected = ' selected="selected"';
        } elseif (($_SESSION['software']['ecommerce']['view_orders']['tax_status'] ?? '') == 'not_tax_exempt') {
            $tax_status_not_tax_exempt_selected = ' selected="selected"';
        }

        // if multi currency is enabled, output miscellaneous fieldset with currency pick list
        if (ECOMMERCE_MULTICURRENCY === true) {
            $output_currency_row =
                '<div class="col-12 col-sm-6 col-md-12 my-1">
                    <label class="form-label">' . lang('Currency') . '</label>
                    <select class="form-select" name="currency_code"><option value="">' . lang('Any') . '</option>' . get_currency_options(($_SESSION['software']['ecommerce']['view_orders']['currency_code'] ?? '')) . '</select>
                </div>';
        } else {
            $output_currency_row = '';
        }
        
        // prepare selection for address type field
        if (($_SESSION['software']['ecommerce']['view_orders']['address_type'] ?? '') == 'any') {
            $address_type_any_selected = ' selected="selected"';
        } elseif ((isset($_SESSION['software']['ecommerce']['view_orders']['address_type']) == true) && (($_SESSION['software']['ecommerce']['view_orders']['address_type'] ?? '') == '')) {
            $address_type_none_selected = ' selected="selected"';
        } elseif (($_SESSION['software']['ecommerce']['view_orders']['address_type'] ?? '') == 'residential') {
            $address_type_residential_selected = ' selected="selected"';
        } elseif (($_SESSION['software']['ecommerce']['view_orders']['address_type'] ?? '') == 'business') {
            $address_type_business_selected = ' selected="selected"';
        }

        // Prepare selection for shipping status field.
        if (($_SESSION['software']['ecommerce']['view_orders']['shipping_status'] ?? '') == 'any') {
            $shipping_status_any_selected = ' selected="selected"';

        } elseif (($_SESSION['software']['ecommerce']['view_orders']['shipping_status'] ?? '') == 'unshipped') {
            $shipping_status_unshipped_selected = ' selected="selected"';

        } elseif (($_SESSION['software']['ecommerce']['view_orders']['shipping_status'] ?? '') == 'shipped') {
            $shipping_status_shipped_selected = ' selected="selected"';
        }

        $output_date_type_field = '';

        // If shipping is enabled then output date type field.
        if (ECOMMERCE_SHIPPING == true) {
            if (($_SESSION['software']['ecommerce']['view_orders']['date_type'] ?? '') == 'order_date') {
                $date_type_order_date_selected = ' selected="selected"';

            } elseif (($_SESSION['software']['ecommerce']['view_orders']['date_type'] ?? '') == 'ship_date') {
                $date_type_ship_date_selected = ' selected="selected"';
            }

            $output_date_type_field = '<select class="form-select" name="date_type"><option value="order_date"' . $date_type_order_date_selected . '>' . lang('Order Date') . '</option><option value="ship_date"' . $date_type_ship_date_selected . '>' . lang('Ship Date') . '</option></select>';
        }

        $output_advanced_filters =
            '<div class="advanced_filters advanced-filter-bar  position-fixed-md"  id="advanced_filters" >
                <div class="p-2 border justify-content-between d-flex flex-wrap header">
                    <p class="m-0"><span class="material-icons pe-1">filter_list</span>' . lang('Filters') . '</p>
                    <a class="btn btn-close " title="' . $output_advanced_filters_label . '" href="view_orders.php?advanced_filters=' . $output_advanced_filters_value . '" ></a>
                </div>
                <form class="advanced-filter-body p-2 pt-0 disable_shortcut" id="search_advanced" action="view_orders.php" method="get">
                    <div class="row">
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Order') . '</h5></div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Order Number') . '</label>
                            <input class="form-control" type="text" name="order_number" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['order_number'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Transaction ID') . '</label>
                            <input class="form-control" type="text" name="transaction_id" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['transaction_id'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Authorization Code') . '</label>
                            <input class="form-control" type="text" name="authorization_code" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['authorization_code'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Special Offer Code') . '</label>
                            <input class="form-control" type="text" name="special_offer_code" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['special_offer_code'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Referral Source Code') . '</label>
                            <input class="form-control" type="text" name="referral_source_code" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['referral_source_code'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Reference Code') . '</label>
                            <input class="form-control" type="text" name="reference_code" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['reference_code'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Tracking Code') . '</label>
                            <input class="form-control" type="text" name="tracking_code" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['tracking_code'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Referring URL') . '</label>
                            <input class="form-control" type="text" name="http_referer" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['http_referer'] ?? '')) . '" />
                        </div>
                        ' . $output_currency_row . '
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Customer\'s IP Address') . '</label>
                            <input class="form-control" type="text" name="ip_address" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['ip_address'] ?? '')) . '" />
                        </div>
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Product') . '</h5></div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Product Name') . '</label>
                            <input class="form-control" type="text" name="product_name" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['product_name'] ?? '')) . '" />
                        </div>
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Payment') . '</h5></div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Payment Method') . '</label>
                            <select class="form-select" name="payment_method"><option value="any"' . $payment_method_any_selected . '>' . lang('Any') . '</option><option value=""' . $payment_method_none_selected . '>' . lang('None') . '</option><option value="Credit/Debit Card"' . $payment_method_credit_debit_card_selected . '>' . lang('Credit/Debit Card') . '</option><option value="PayPal Express Checkout"' . $payment_method_paypal_express_checkout_selected . '>' . lang('PayPal Express Checkout') . '</option><option value="Offline Payment"' . $payment_method_offline_selected . '>' . lang('Offline Payment') . '</option></select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Card Type') . '</label>
                            <input class="form-control" type="text" name="card_type" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['card_type'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Cardholder') . '</label>
                            <input class="form-control" type="text" name="cardholder" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['cardholder'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Card Number') . '</label>
                            <input class="form-control" type="text" name="card_number" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['card_number'] ?? '')) . '" />
                        </div>
                        ' . $output_affiliate . '
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Billing') . '</h5></div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Custom Field') . '  #1</label>
                            <input class="form-control" type="text" name="custom_field_1" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['custom_field_1'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Custom Field') . '  #2</label>
                            <input class="form-control" type="text" name="custom_field_2" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['custom_field_2'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Salutation') . '</label>
                            <input class="form-control" type="text" name="billing_salutation" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_salutation'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('First Name') . '</label>
                            <input class="form-control" type="text" name="billing_first_name" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_first_name'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Last Name') . '</label>
                            <input class="form-control" type="text" name="billing_last_name" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_last_name'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Company') . '</label>
                            <input class="form-control" type="text" name="billing_company" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_company'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Address') . ' 1</label>
                            <input class="form-control" type="text" name="billing_address_1" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_address_1'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Address') . ' 2</label>
                            <input class="form-control" type="text" name="billing_address_2" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_address_2'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('City') . '</label>
                            <input class="form-control" type="text" name="billing_city" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_city'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('State') . '</label>
                            <input class="form-control" type="text" name="billing_state" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_state'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Zip Code') . '</label>
                            <input class="form-control" type="text" name="billing_zip_code" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_zip_code'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Country') . '</label>
                            <input class="form-control" type="text" name="billing_country" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_country'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Phone') . '</label>
                            <input class="form-control" type="text" name="billing_phone_number" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_phone_number'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Fax') . '</label>
                            <input class="form-control" type="text" name="billing_fax_number" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_fax_number'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Email') . '</label>
                            <input class="form-control" type="text" name="billing_email_address" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['billing_email_address'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Opt-In Status') . '</label>
                            <select class="form-select" name="opt_in_status"><option value="any"' . $opt_in_status_any_selected . '>' . lang('Any') . '</option><option value="opt_in"' . $opt_in_status_opt_in_selected . '>' . lang('Opt-In') . '</option><option value="opt_out"' . $opt_in_status_opt_out_selected . '>' . lang('Opt-Out') . '</option></select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('PO Number') . '</label>
                            <input class="form-control" type="text" name="po_number" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['po_number'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Tax Status') . '</label>
                            <select class="form-select" name="tax_status"><option value="any"' . $tax_status_any_selected . '>' . lang('Any') . '</option><option value="tax_exempt"' . $tax_status_tax_exempt_selected . '>' . lang('Tax-Exempt') . '</option><option value="not_tax_exempt"' . $tax_status_not_tax_exempt_selected . '>' . lang('Not Tax-Exempt') . '</option></select>
                        </div>
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Shipping') . '</h5></div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Salutation') . '</label>
                            <input class="form-control" type="text" name="shipping_salutation" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_salutation'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('First Name') . '</label>
                            <input class="form-control" type="text" name="shipping_first_name" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_first_name'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Last Name') . '</label>
                            <input class="form-control" type="text" name="shipping_last_name" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_last_name'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Company') . '</label>
                            <input class="form-control" type="text" name="shipping_company" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_company'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Address') . ' 1</label>
                            <input class="form-control" type="text" name="shipping_address_1" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_address_1'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Address') . ' 2</label>
                            <input class="form-control" type="text" name="shipping_address_2" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_address_2'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('City') . '</label>
                            <input class="form-control" type="text" name="shipping_city" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_city'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('State') . '</label>
                            <input class="form-control" type="text" name="shipping_state" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_state'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Zip Code') . '</label>
                            <input class="form-control" type="text" name="shipping_zip_code" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_zip_code'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Country') . '</label>
                            <input class="form-control" type="text" name="shipping_country" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_country'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Address Type') . '</label>
                            <select class="form-select" name="address_type"><option value="any"' . $address_type_any_selected . '>' . lang('Any') . '</option><option value=""' . $address_type_none_selected . '>' . lang('None') . '</option><option value="residential"' . $address_type_residential_selected . '>' . lang('Residential') . '</option><option value="business"' . $address_type_business_selected . '>' . lang('Business') . '</option></select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Phone') . '</label>
                            <input class="form-control" type="text" name="shipping_phone_number" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_phone_number'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Ship to Name') . '</label>
                            <input class="form-control" type="text" name="ship_to_name" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['ship_to_name'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Arrival Date Code') . '</label>
                            <input class="form-control" type="text" name="arrival_date_code" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['arrival_date_code'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Shipping Method Code') . '</label>
                            <input class="form-control" type="text" name="shipping_method_code" value="' . h(($_SESSION['software']['ecommerce']['view_orders']['shipping_method_code'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label class="form-label">' . lang('Shipping Status') . '</label>
                            <select class="form-select" name="shipping_status"><option value="any"' . $shipping_status_any_selected . '>' . lang('Any') . '</option><option value="unshipped"' . $shipping_status_unshipped_selected . '>' . lang('Unshipped') . '</option><option value="shipped"' . $shipping_status_shipped_selected . '>' . lang('Shipped') . '</option></select>
                        </div>
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Date Range') . '</h5></div>
                        <div class="col-12">' . $output_date_type_field . '</div>
                        <div class="col-12">
                            <label class="form-label">' . lang('From') . '</label>
                            <select class="form-select my-1" name="start_month">' . select_month(($_SESSION['software']['ecommerce']['view_orders']['start_month'] ?? '')) . '</select>
                            <div class="input-group input-group-sm">
                                <select class="form-select my-1" name="start_day">' . select_day(($_SESSION['software']['ecommerce']['view_orders']['start_day'] ?? '')) . '</select>
                                <select class="form-select my-1" name="start_year">' . select_year($years, ($_SESSION['software']['ecommerce']['view_orders']['start_year'] ?? '')) . '</select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">' . lang('To') . '</label>
                            <select class="form-select my-1" name="stop_month">' . select_month(($_SESSION['software']['ecommerce']['view_orders']['stop_month'] ?? '')) . '</select>
                            <div class="input-group input-group-sm">
                                <select class="form-select my-1" name="stop_day">' . select_day(($_SESSION['software']['ecommerce']['view_orders']['stop_day'] ?? '')) . '</select>
                                <select class="form-select my-1" name="stop_year">' . select_year($years, ($_SESSION['software']['ecommerce']['view_orders']['stop_year'] ?? '')) . '</select>
                            </div>
                        </div>
                        <div class="col-12 text-center position-sticky my-2" style="bottom:.5rem;">
                            <button type="submit" name="submit_data" value="Update" data-loading-content="' . lang('Updating') . '" class="btn btn-primary my-1"><i class="material-icons me-2">sync</i>' . lang('Update') . '</button>
                        </div>
                                
                    </div>
                </form>
            </div>';
    }
	
	// Get Payment Gateway for button bar output
	$output_gateway_buttons = '';
    $output_payment_mode = '';
	if(ECOMMERCE_PAYMENT_GATEWAY == 'Iyzipay'){
		// if test or live mode for iyzipay gateway. 
		if (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test') {
			$output_payment_mode = 'sandbox-merchant';
		}else {
            $output_payment_mode = 'merchant';
		}
        
		$output_gateway_buttons = '<button type="button" class="btn btn-sm btn-secondary m-1" title="' . ECOMMERCE_PAYMENT_GATEWAY . '" onclick="window.open(\'https://' . $output_payment_mode . '.iyzipay.com/dashboard\', \'popup\', \'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500\');"><span class="material-icons me-2">account_balance</span>' . lang(array('string'=>'View Orders') ) . '</button>';
	}



    // total of all online sales.
    $online_orders_total = 0;
    $online_orders_total_all = 0;
    $query = "SELECT total FROM orders ";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    while ($local_sale_row = mysqli_fetch_assoc($result)) {
        $online_orders_total =  $local_sale_row['total'] / 100 + $online_orders_total;
    }
    $online_orders_total_all = prepare_amount($online_orders_total);

    // Money already taken from customers that has not gone back yet.
    //
    // Site-wide like the online total beside it, NOT filtered by the date
    // range or the status picker: a debt does not stop existing because the
    // operator is looking at last year. Filtering it would also make the
    // figure vanish exactly when someone is browsing history, which is when
    // an unnoticed refund is most likely to be sitting there.
    //
    // Summed on the same basis as the other two cards (one SUM over
    // orders.total through prepare_amount), so all three read alike; on a
    // multi-currency shop all three are equally approximate.
    //
    // The column arrives with 2026.1.27. Without it the card shows zero
    // rather than disappearing — an install that cannot answer the question
    // is not an install that owes refunds.
    $refund_pending_total = 0;

    if ($refund_columns_exist) {
        $refund_pending_total = ((float) db_value(
            "SELECT COALESCE(SUM(total), 0)
             FROM orders
             WHERE refund_status IN ('manual_required', 'failed', 'pending')")) / 100;
    }

    $refund_pending_total_all = prepare_amount($refund_pending_total);

    $output .=
    pg_page_shell(
        array(
            'title'=> lang('View Orders'),
            'extra classes'=>'store',
            'icon'=>'store', 
            'heading'=>lang('View Orders'),
            'cancel'=>false
        )
    ) . '     
    ' . $output_advanced_filters . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-8 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('View and export website orders.') . '" title="' . lang('All Orders') . '">' . lang('All Orders') . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <form id="search" action="view_orders.php" class="disable_shortcut" method="get">
                                ' . $output_gateway_buttons . '  
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <button type="submit" name="submit_data" value="Export Orders (multiple files)" title="' . lang('Multiple files') . '" class="btn btn-link link-secondary py-0 m-1"><span class="material-icons me-1">inventory_2</span>' . lang(array('string'=>'Export') ) . '</button>
                                    <button type="submit" name="submit_data" value="Export Orders (single file)" title="' . lang('Single file') . '" class="btn btn-link link-secondary py-0 m-1"><span class="bi bi-file-earmark-arrow-down bi-me-2"></span>' . lang(array('string'=>'Export') ) . '</button>
                                </div>
                            </form>
                        </nav>
                    </div> 
                    <div class="col-12 col-sm-12 col-md-6 col-xl-4 ">
                        <div class="row justify-content-center justify-content-md-end">
                            <form id="search_form" action="view_orders.php" method="get" class="search_form disable_shortcut col-auto">
                                <div class="input-group input-group-sm">
                                    <a class="btn btn-sm  my-1 ' . $output_advanced_filters_class . '" data-loading-content=" " title="' . $output_advanced_filters_label . '" href="view_orders.php?advanced_filters=' . $output_advanced_filters_value . '" ><i class="material-icons">'. $advanced_filters_icon . '</i></a>
                                    <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Content that viewed') . '" for="filter_select">visibility</label>
                                    <select id="status" name="status" class="form-select mt-1 mb-1" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . $output_status_options . '</select>
                                    <select id="type" name="type" class="form-select mt-1 mb-1" title="' . lang('Order Type') . '" onchange="submit_form(\'search_form\')">' . $output_type_options . '</select>
                                </div>
                                <div class="row justify-content-center justify-content-md-end">
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_orders.php?start_month=' . $decrease_year['start_month'] . '&start_day=' . $decrease_year['start_day'] . '&start_year=' . $decrease_year['start_year'] . '&stop_month=' . $decrease_year['stop_month'] . '&stop_day=' . $decrease_year['stop_day'] . '&stop_year=' . $decrease_year['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_orders.php?start_month=' . $current_year['start_month'] . '&start_day=' . $current_year['start_day'] . '&start_year=' . $current_year['start_year'] . '&stop_month=' . $current_year['stop_month'] . '&stop_day=' . $current_year['stop_day'] . '&stop_year=' . $current_year['stop_year'] . '">' . lang('Year') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_orders.php?start_month=' . $increase_year['start_month'] . '&start_day=' . $increase_year['start_day'] . '&start_year=' . $increase_year['start_year'] . '&stop_month=' . $increase_year['stop_month'] . '&stop_day=' . $increase_year['stop_day'] . '&stop_year=' . $increase_year['stop_year'] . '">></a>
                                    </div>
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_orders.php?start_month=' . $decrease_month['start_month'] . '&start_day=' . $decrease_month['start_day'] . '&start_year=' . $decrease_month['start_year'] . '&stop_month=' . $decrease_month['stop_month'] . '&stop_day=' . $decrease_month['stop_day'] . '&stop_year=' . $decrease_month['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_orders.php?start_month=' . $current_month['start_month'] . '&start_day=' . $current_month['start_day'] . '&start_year=' . $current_month['start_year'] . '&stop_month=' . $current_month['stop_month'] . '&stop_day=' . $current_month['stop_day'] . '&stop_year=' . $current_month['stop_year'] . '">' . lang('Month') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_orders.php?start_month=' . $increase_month['start_month'] . '&start_day=' . $increase_month['start_day'] . '&start_year=' . $increase_month['start_year'] . '&stop_month=' . $increase_month['stop_month'] . '&stop_day=' . $increase_month['stop_day'] . '&stop_year=' . $increase_month['stop_year'] . '">></a>
                                    </div>
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">    
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_orders.php?start_month=' . $decrease_week['start_month'] . '&start_day=' . $decrease_week['start_day'] . '&start_year=' . $decrease_week['start_year'] . '&stop_month=' . $decrease_week['stop_month'] . '&stop_day=' . $decrease_week['stop_day'] . '&stop_year=' . $decrease_week['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_orders.php?start_month=' . $current_week['start_month'] . '&start_day=' . $current_week['start_day'] . '&start_year=' . $current_week['start_year'] . '&stop_month=' . $current_week['stop_month'] . '&stop_day=' . $current_week['stop_day'] . '&stop_year=' . $current_week['stop_year'] . '">' . lang('Week') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_orders.php?start_month=' . $increase_week['start_month'] . '&start_day=' . $increase_week['start_day'] . '&start_year=' . $increase_week['start_year'] . '&stop_month=' . $increase_week['stop_month'] . '&stop_day=' . $increase_week['stop_day'] . '&stop_year=' . $increase_week['stop_year'] . '">></a>
                                    </div>
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">    
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_orders.php?start_month=' . $decrease_day['start_month'] . '&start_day=' . $decrease_day['start_day'] . '&start_year=' . $decrease_day['start_year'] . '&stop_month=' . $decrease_day['stop_month'] . '&stop_day=' . $decrease_day['stop_day'] . '&stop_year=' . $decrease_day['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_orders.php?start_month=' . $current_day['start_month'] . '&start_day=' . $current_day['start_day'] . '&start_year=' . $current_day['start_year'] . '&stop_month=' . $current_day['stop_month'] . '&stop_day=' . $current_day['stop_day'] . '&stop_year=' . $current_day['stop_year'] . '">' . lang('Day') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_orders.php?start_month=' . $increase_day['start_month'] . '&start_day=' . $increase_day['start_day'] . '&start_year=' . $increase_day['start_year'] . '&stop_month=' . $increase_day['stop_month'] . '&stop_day=' . $increase_day['stop_day'] . '&stop_year=' . $increase_day['stop_year'] . '">></a>
                                    </div>    
                                </div>
                                <p class="text-center text-md-end p-0 m-0">
                                    <span class="badge text-dark fw-light border-2">    ' . $output_date_range_time . '</span>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 col-md-6 col-lg-3 p-1">
                       <div class="card border-0 border-4 h-100  shadow-sm">
                            <div class="card-body">
                                <h4 class="mb-0">' . $online_orders_total_all . '</h4>
                                <span class="material-icons text-secondary" style="position: absolute;right: 0;bottom: 0;font-size: 5rem;opacity: 0.05;line-height: 5rem;">account_balance</span>
                            </div>
                            <div class="card-footer bg-reset border-0">
                                <small>' . lang('Total Sales Amount') . ' (' . lang('Online') . ')</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3 p-1">
                        <div class="card border-0 border-4 h-100  shadow-sm">
                            <div class="card-body">
                                <h4 class="mb-0' . (($refund_pending_total > 0) ? ' text-danger' : '') . '">' . $refund_pending_total_all . '</h4>
                                <span class="material-icons text-secondary" style="position: absolute;right: 0;bottom: 0;font-size: 5rem;opacity: 0.05;line-height: 5rem;">currency_exchange</span>
                            </div>
                            <div class="card-footer bg-reset border-0">
                                <small>' . lang('Refund Pending') . '</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3 offset-lg-3 p-1">
                        <div class="card border-0 border-4 h-100  shadow-sm">
                            <div class="card-body">
                                <h4 class="mb-0">' . $filter_totals . '</h4>
                                <span class="material-icons text-secondary" style="position: absolute;right: 0;bottom: 0;font-size: 5rem;opacity: 0.05;line-height: 5rem;">filter_list</span>
                            </div>
                            <div class="card-footer bg-reset border-0">
                                <small>' . lang('Total Sales Amount') . ' (' . lang('Current filter') . ')</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <form name="form"  action="edit_orders.php" method="post" class="view_orders"> 
                            ' . get_token_field() . ' 
                            <input type="hidden" name="action">
                            <table class="chart table-hover table " style="width:100%;display:none">
                                <thead>
                                    <tr>
                                        <th class="noVis">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                                            </div>
                                        </th>
                                        <th class="noVis">' . lang('Action') . '</th> 
                                        <th>' . get_column_heading(lang('First Name'), ($_SESSION['software']['ecommerce']['view_orders']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Last Name'), ($_SESSION['software']['ecommerce']['view_orders']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Status'), ($_SESSION['software']['ecommerce']['view_orders']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['order'] ?? '')) . '</th>
                                        <th>' . lang('Type') . '</th>
                                        <th>' . get_column_heading(lang('Order Number'), ($_SESSION['software']['ecommerce']['view_orders']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['order'] ?? '')) . '</th>
                                        <th class="text-end">' . get_column_heading(lang('Total'), ($_SESSION['software']['ecommerce']['view_orders']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('User'), ($_SESSION['software']['ecommerce']['view_orders']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Tracking Code'), ($_SESSION['software']['ecommerce']['view_orders']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(MEMBER_ID_LABEL, ($_SESSION['software']['ecommerce']['view_orders']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['order'] ?? '')) . '</th>
                                        ' . $output_affiliate_heading . '
                                        <th>' . lang('Card Data') . '</th>
                                        <th>' . get_column_heading(lang('Order Date'), ($_SESSION['software']['ecommerce']['view_orders']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_orders']['order'] ?? '')) . '</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . $output_rows . '
                                </tbody>
                            </table>
                            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                                <div class="container">
                                    <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                        ' . $output_generate_xlsl_button . '
                                        <button type="button" value="Remove Card Data for Selected" class="btn mb-1 mt-1 btn-primary disabled" data-loading-content="' . lang(array('string'=>'Removing') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: {var:1} will be permanently removed from the selected order(s).','vars'=>array(lang('Card Data')))) . '"><span class="material-icons me-2">credit_card_off</span>' . lang(array('string'=>'Remove Card Data for Selected') ) . '</button>
                                        <button type="button" value="Cancel Selected" class="btn mb-1 mt-1 btn-warning disabled" data-loading-content="' . lang(array('string'=>'Cancelling') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected order(s) will be cancelled. This cannot be undone.')) . '"><i class="bi bi-x-octagon me-2"></i>' . lang('Cancel Selected') . '</button>
                                        <button type="button" value="Delete Selected" class="btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('orders')))) . '"><span class="material-icons me-2">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
                                    </div>
                                </div>
                            </nav>
                            ' . $output_xlsl_setup_modal . '
                        </form>

                        <!-- Per-row cancel POST target. Deliberately a sibling of the
                             page form above, never a child: nested forms are dropped by
                             the HTML parser and the button would silently submit the
                             wrong form. One shared form serves every row; the JS below
                             fills in order_id before submitting. -->
                        <form method="post" action="cancel_order.php" id="pg-admin-cancel-form" class="d-none">
                            ' . get_token_field() . '
                            <input type="hidden" name="order_id" value="">
                            <input type="hidden" name="cancellation_reason" value="">
                            <input type="hidden" name="admin_context" value="view_orders">
                            <input type="hidden" name="send_to" value="' . h(PATH . SOFTWARE_DIRECTORY . '/view_orders.php') . '">
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <script>
        // Per-row "İptal" button — confirm via pgConfirm(), then post the shared
        // #pg-admin-cancel-form (which lives OUTSIDE the page form; see the
        // comment where the button markup is built).
        //
        // The listener is delegated from document rather than bound per button:
        // this table is a DataTable, so rows are detached / re-attached on
        // pagination, sorting and search. Delegation keeps the buttons working
        // on every page without re-binding after each draw.
        //
        // The buttons carry class "no-submit" so the global click handler in
        // backend.src.js leaves them alone.
        (function () {
            document.addEventListener("click", function (ev) {
                var btn = ev.target && ev.target.closest ? ev.target.closest(".pg-admin-cancel-btn") : null;
                if (!btn) return;

                ev.preventDefault();
                ev.stopPropagation();

                var form = document.getElementById("pg-admin-cancel-form");
                var orderId = btn.getAttribute("data-order-id");
                if (!form || !orderId) return;

                var label = btn.getAttribute("data-order-number") || orderId;
                var question = ' . json_encode(lang('Are you sure you want to cancel this order? This cannot be undone.')) . ' + " (#" + label + ")";

                function submitCancel() {
                    form.elements["order_id"].value = orderId;
                    form.submit();
                }

                if (typeof window.pgConfirm !== "function") {
                    if (window.confirm(question)) submitCancel();
                    return;
                }
                window.pgConfirm({
                    title: ' . json_encode(lang('Cancel Order')) . ',
                    message: question,
                    confirmText: ' . json_encode(lang('Cancel Order')) . ',
                    cancelText: ' . json_encode(lang('Cancel')) . ',
                    variant: "danger"
                }).then(function (ok) {
                    if (ok) submitCancel();
                });
            });
        })();
        </script>
    </main>' .
    output_footer();

    print $output;
    
    $liveform->remove_form('view_orders');
}

class zipfile
{

    var $datasec = array(); // array to store compressed data
    var $ctrl_dir = array(); // central directory
    var $eof_ctrl_dir = "\x50\x4b\x05\x06\x00\x00\x00\x00"; //end of Central directory record
    var $old_offset = 0;

    function add_dir($name)

    // adds "directory" to archive - do this before putting any files in directory!
    // $name - name of directory... like this: "path/"
    // ...then you can add files using add_file with names like "path/file.txt"
    {
        $name = str_replace("\\", "/", $name);

        $fr = "\x50\x4b\x03\x04";
        $fr .= "\x0a\x00";    // ver needed to extract
        $fr .= "\x00\x00";    // gen purpose bit flag
        $fr .= "\x00\x00";    // compression method
        $fr .= "\x00\x00\x00\x00"; // last mod time and date

        $fr .= pack("V",0); // crc32
        $fr .= pack("V",0); //compressed filesize
        $fr .= pack("V",0); //uncompressed filesize
        $fr .= pack("v", strlen($name) ); //length of pathname
        $fr .= pack("v", 0 ); //extra field length
        $fr .= $name;
        // end of "local file header" segment

        // no "file data" segment for path

        // "data descriptor" segment (optional but necessary if archive is not served as file)
        $fr .= pack("V",$crc); //crc32
        $fr .= pack("V",$c_len); //compressed filesize
        $fr .= pack("V",$unc_len); //uncompressed filesize

        // add this entry to array
        $this -> datasec[] = $fr;

        $new_offset = strlen(implode("", $this->datasec));

        // ext. file attributes mirrors MS-DOS directory attr byte, detailed
        // at http://support.microsoft.com/support/kb/articles/Q125/0/19.asp

        // now add to central record
        $cdrec = "\x50\x4b\x01\x02";
        $cdrec .="\x00\x00";    // version made by
        $cdrec .="\x0a\x00";    // version needed to extract
        $cdrec .="\x00\x00";    // gen purpose bit flag
        $cdrec .="\x00\x00";    // compression method
        $cdrec .="\x00\x00\x00\x00"; // last mod time & date
        $cdrec .= pack("V",0); // crc32
        $cdrec .= pack("V",0); //compressed filesize
        $cdrec .= pack("V",0); //uncompressed filesize
        $cdrec .= pack("v", strlen($name) ); //length of filename
        $cdrec .= pack("v", 0 ); //extra field length
        $cdrec .= pack("v", 0 ); //file comment length
        $cdrec .= pack("v", 0 ); //disk number start
        $cdrec .= pack("v", 0 ); //internal file attributes
        $ext = "\x00\x00\x10\x00";
        $ext = "\xff\xff\xff\xff";
        $cdrec .= pack("V", 16 ); //external file attributes  - 'directory' bit set

        $cdrec .= pack("V", $this -> old_offset ); //relative offset of local header
        $this -> old_offset = $new_offset;

        $cdrec .= $name;
        // optional extra field, file comment goes here
        // save to array
        $this -> ctrl_dir[] = $cdrec;


    }


    function add_file($data, $name)

    // adds "file" to archive
    // $data - file contents
    // $name - name of file in archive. Add path if your want

    {
        $name = str_replace("\\", "/", $name);
        //$name = str_replace("\\", "\\\\", $name);

        $fr = "\x50\x4b\x03\x04";
        $fr .= "\x14\x00";    // ver needed to extract
        $fr .= "\x00\x00";    // gen purpose bit flag
        $fr .= "\x08\x00";    // compression method
        $fr .= "\x00\x00\x00\x00"; // last mod time and date

        $unc_len = strlen($data);
        $crc = crc32($data);
        $zdata = gzcompress($data);
        $zdata = substr( substr($zdata, 0, strlen($zdata) - 4), 2); // fix crc bug
        $c_len = strlen($zdata);
        $fr .= pack("V",$crc); // crc32
        $fr .= pack("V",$c_len); //compressed filesize
        $fr .= pack("V",$unc_len); //uncompressed filesize
        $fr .= pack("v", strlen($name) ); //length of filename
        $fr .= pack("v", 0 ); //extra field length
        $fr .= $name;
        // end of "local file header" segment

        // "file data" segment
        $fr .= $zdata;

        // "data descriptor" segment (optional but necessary if archive is not served as file)
        $fr .= pack("V",$crc); //crc32
        $fr .= pack("V",$c_len); //compressed filesize
        $fr .= pack("V",$unc_len); //uncompressed filesize

        // add this entry to array
        $this -> datasec[] = $fr;

        $new_offset = strlen(implode("", $this->datasec));

        // now add to central directory record
        $cdrec = "\x50\x4b\x01\x02";
        $cdrec .="\x00\x00";    // version made by
        $cdrec .="\x14\x00";    // version needed to extract
        $cdrec .="\x00\x00";    // gen purpose bit flag
        $cdrec .="\x08\x00";    // compression method
        $cdrec .="\x00\x00\x00\x00"; // last mod time & date
        $cdrec .= pack("V",$crc); // crc32
        $cdrec .= pack("V",$c_len); //compressed filesize
        $cdrec .= pack("V",$unc_len); //uncompressed filesize
        $cdrec .= pack("v", strlen($name) ); //length of filename
        $cdrec .= pack("v", 0 ); //extra field length
        $cdrec .= pack("v", 0 ); //file comment length
        $cdrec .= pack("v", 0 ); //disk number start
        $cdrec .= pack("v", 0 ); //internal file attributes
        $cdrec .= pack("V", 32 ); //external file attributes - 'archive' bit set

        $cdrec .= pack("V", $this -> old_offset ); //relative offset of local header
//        echo "old offset is ".$this->old_offset.", new offset is $new_offset<br>";
        $this -> old_offset = $new_offset;

        $cdrec .= $name;
        // optional extra field, file comment goes here
        // save to central directory
        $this -> ctrl_dir[] = $cdrec;
    }

    function file() { // dump out file
        $data = implode("", $this -> datasec);
        $ctrldir = implode("", $this -> ctrl_dir);

        return
            $data.
            $ctrldir.
            $this -> eof_ctrl_dir.
            pack("v", sizeof($this -> ctrl_dir)).     // total # of entries "on this disk"
            pack("v", sizeof($this -> ctrl_dir)).     // total # of entries overall
            pack("V", strlen($ctrldir)).             // size of central dir
            pack("V", strlen($data)).                 // offset to start of central dir
            "\x00\x00";                             // .zip file comment length
    }
}