<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Guest parcel lookup (track_shipment.php): a visitor without an account
 * types an order number and the order's billing e-mail and sees the order's
 * parcels - carrier, tracking number and link, status, shipped and delivered
 * dates - read from the shipment rows through pg_shipment_view(), as the
 * order screens read them. Nothing else of the order is shown: no prices,
 * items, addresses or names.
 *
 * A wrong number and a wrong address give the same answer, so the page
 * tells nobody which orders exist. Every lookup sent with the session's
 * token is counted per visitor address (waf_rate, scope 'shiplookup'):
 * PG_SHIPMENT_LOOKUP_LIMIT an hour, the eleventh refused, which keeps the
 * two fields from being guessed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SHIPMENT_LOOKUP_LIMIT')) {
    define('PG_SHIPMENT_LOOKUP_LIMIT', 10);
}

if (!defined('PG_SHIPMENT_LOOKUP_WINDOW')) {
    define('PG_SHIPMENT_LOOKUP_WINDOW', 3600);
}

/**
 * An order number as typed, read as its digits: spaces and a leading # are
 * dropped. Anything else (letters, quotes, over 20 digits, zero) is ''.
 *
 * @param  mixed  $value
 * @return string
 */
function pg_shipment_lookup_order_number($value)
{
    if (!is_scalar($value)) {
        return '';
    }

    $number = ltrim((string) preg_replace('/\s+/u', '', (string) $value), '#');

    // orders.order_number is an unsigned BIGINT: at most 20 digits.
    if (!preg_match('/^[0-9]{1,20}$/', $number)) {
        return '';
    }

    $number = ltrim($number, '0');

    return $number;
}

/**
 * An e-mail address as typed: trimmed and lower-cased; '' when it is not
 * one or is longer than orders.billing_email_address holds.
 *
 * @param  mixed  $value
 * @return string
 */
function pg_shipment_lookup_email($value)
{
    if (!is_scalar($value)) {
        return '';
    }

    $email = mb_strtolower(trim((string) $value), 'UTF-8');
    $at = strrpos($email, '@');

    if (($email === '') || (mb_strlen($email, 'UTF-8') > 100) || ($at === false) || ($at === 0) || ($at === strlen($email) - 1)) {
        return '';
    }

    return $email;
}

/**
 * Whether an order is the one the visitor asked for: placed (not still in
 * the cart) and billed to the address typed, in any capitals.
 *
 * @param  mixed  $order an orders row with status and billing_email_address
 * @param  string $email pg_shipment_lookup_email()
 * @return bool
 */
function pg_shipment_lookup_matches($order, $email)
{
    if (!is_array($order) || !isset($order['status']) || ($order['status'] === 'incomplete')) {
        return false;
    }

    $email = (string) $email;
    $billing = pg_shipment_lookup_email($order['billing_email_address'] ?? '');

    if (($email === '') || ($billing === '')) {
        return false;
    }

    return hash_equals($billing, $email);
}

/**
 * The order among those that carry the number typed whose billing address
 * matches; 0 for none.
 *
 * @param  array  $orders rows with id, status, billing_email_address
 * @param  string $email  pg_shipment_lookup_email()
 * @return int
 */
function pg_shipment_lookup_pick_order($orders, $email)
{
    foreach ((array) $orders as $order) {
        if (pg_shipment_lookup_matches($order, $email)) {
            return (int) $order['id'];
        }
    }

    return 0;
}

/**
 * The answer: each numbered parcel of the order's addresses, oldest address
 * first, holding only what the parcel line shows. A row whose address the
 * order does not list is left out.
 *
 * @param  array  $ship_tos the order's ship_tos rows (id, shipping_method_code)
 * @param  array  $rows     ship_to id => rows of pg_shipment_rows()
 * @param  string $default  the site's default carrier (pg_shipping_default_carrier())
 * @return array  each: number, carrier_name, url, status, shipped_at, delivered_at
 */
function pg_shipment_lookup_parcels($ship_tos, $rows, $default)
{
    $parcels = array();

    foreach ((array) $ship_tos as $ship_to) {
        $id = (int) ($ship_to['id'] ?? 0);

        if (($id <= 0) || empty($rows[$id])) {
            continue;
        }

        foreach ((array) $rows[$id] as $row) {
            $view = pg_shipment_view($row, (string) ($ship_to['shipping_method_code'] ?? ''), (string) $default);

            if ($view['number'] === '') {
                continue;
            }

            $parcels[] = array(
                'number'       => $view['number'],
                'carrier_name' => $view['carrier_name'],
                'url'          => $view['url'],
                'status'       => $view['status'],
                'shipped_at'   => (int) ($row['shipped_at'] ?? 0),
                'delivered_at' => (int) ($row['delivered_at'] ?? 0),
            );
        }
    }

    return $parcels;
}

/**
 * Whether a lookup count is past the hourly limit. A counter that could not
 * be written returns 0 and never refuses.
 *
 * @param  int  $hits the count waf_rate_record() returned, this lookup included
 * @return bool
 */
function pg_shipment_lookup_over_limit($hits)
{
    return ((int) $hits > PG_SHIPMENT_LOOKUP_LIMIT);
}

/**
 * Whether a visitor address gets a counter of its own: a public address
 * that is not one of the Trusted Proxies. Behind a proxy that is not listed
 * every visitor would come from one private address and share one bucket,
 * as with the form limits (pg_cf_visitor_address_counted()).
 *
 * @param  string $ip
 * @param  bool   $infrastructure waf_ip_is_infrastructure()
 * @return bool
 */
function pg_shipment_lookup_address_counted($ip, $infrastructure)
{
    if ($infrastructure) {
        return false;
    }

    return filter_var((string) $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/**
 * Counts one lookup of the visitor's address and says whether it is refused.
 * Logged like every other limit; works with the firewall off and fails open.
 *
 * @return bool
 */
function pg_shipment_lookup_throttled()
{
    if (!function_exists('waf_rate_record') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return false;
    }

    $ip = (string) waf_client_ip();
    $infrastructure = function_exists('waf_ip_is_infrastructure') && waf_ip_is_infrastructure($ip);

    if (!pg_shipment_lookup_address_counted($ip, $infrastructure)) {
        return false;
    }

    $hits = waf_rate_record(waf_ip_subject($ip), 'shiplookup', PG_SHIPMENT_LOOKUP_WINDOW);

    if (function_exists('waf_rate_sweep') && (mt_rand(1, 50) === 1)) {
        waf_rate_sweep();
    }

    if (!pg_shipment_lookup_over_limit($hits)) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-shipment-lookup', 'rate', 5, 'track_shipment.php', PG_SHIPMENT_LOOKUP_LIMIT . '/60min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-shipment-lookup'));
    }

    return true;
}

/**
 * The parcels of the order the number and address name, or null when they
 * name none (the same for a wrong number and a wrong address).
 *
 * @param  string     $number pg_shipment_lookup_order_number()
 * @param  string     $email  pg_shipment_lookup_email()
 * @return array|null
 */
function pg_shipment_lookup($number, $email)
{
    if (($number === '') || ($email === '')) {
        return null;
    }

    $orders = db_items(
        "SELECT id, status, billing_email_address FROM orders
         WHERE order_number = '" . e($number) . "' AND status != 'incomplete'
         ORDER BY id DESC LIMIT 20");

    $order_id = pg_shipment_lookup_pick_order($orders, $email);

    if ($order_id <= 0) {
        return null;
    }

    $ship_tos = db_items("SELECT id, shipping_method_code FROM ship_tos WHERE order_id = '" . $order_id . "' ORDER BY id ASC");
    $rows = array();

    foreach ($ship_tos as $ship_to) {
        $rows[(int) $ship_to['id']] = pg_shipment_rows((int) $ship_to['id']);
    }

    return pg_shipment_lookup_parcels($ship_tos, $rows, pg_shipping_default_carrier());
}

/**
 * A day as the page prints it, in the site's date order.
 *
 * @param  int    $timestamp
 * @return string '' for none
 */
function pg_shipment_lookup_date_text($timestamp)
{
    if ((int) $timestamp <= 0) {
        return '';
    }

    return date((defined('DATE_FORMAT') && (DATE_FORMAT == 'month_day')) ? 'n/j/Y' : 'j/n/Y', (int) $timestamp);
}

/**
 * The parcel list: one card per parcel, the number linked to the carrier's
 * page when one is known.
 *
 * @param  array  $parcels pg_shipment_lookup_parcels()
 * @return string HTML
 */
function pg_shipment_lookup_result_html($parcels)
{
    if (!$parcels) {
        return '<p class="pg-track-note">' . h(lang('No parcel has been shipped for this order yet.')) . '</p>';
    }

    $html = '<ul class="pg-track-list">';

    foreach ($parcels as $parcel) {
        $number = h($parcel['number']);

        if ($parcel['url'] !== '') {
            $number = '<a href="' . h($parcel['url']) . '" target="_blank" rel="noopener noreferrer">' . $number . '</a>';
        }

        $lines = array();
        $shipped = pg_shipment_lookup_date_text($parcel['shipped_at']);
        $delivered = pg_shipment_lookup_date_text($parcel['delivered_at']);

        if ($shipped !== '') {
            $lines[] = h(lang('Shipped')) . ': ' . h($shipped);
        }

        if ($delivered !== '') {
            $lines[] = h(lang('Delivered')) . ': ' . h($delivered);
        }

        $html .= '<li>'
            . '<div class="pg-track-head"><strong>' . h(($parcel['carrier_name'] !== '') ? $parcel['carrier_name'] : lang('Carrier unknown')) . '</strong>'
            . '<span class="pg-track-status">' . h(pg_shipment_status_label($parcel['status'])) . '</span></div>'
            . '<div>' . h(lang('Tracking number')) . ': ' . $number . '</div>'
            . ($lines ? '<div class="pg-track-dates">' . implode(' · ', $lines) . '</div>' : '')
            . '</li>';
    }

    return $html . '</ul>';
}

/**
 * The whole page: the form, the values the visitor typed kept in it, and
 * above it the answer or the refusal. Standalone, like the legal document
 * page, so it works whatever the site's design holds.
 *
 * @param  string $number   as typed, shown back
 * @param  string $email    as typed, shown back
 * @param  string $answer   HTML of the answer, '' before a lookup
 * @param  string $language the page's language code
 * @return string HTML
 */
function pg_shipment_lookup_page_html($number, $email, $answer, $language)
{
    $title = lang('Track Your Shipment');

    return '<!DOCTYPE html>
<html lang="' . h((string) $language) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . h($title) . '</title>
<style>
body { margin: 0; padding: 1.5rem 1rem 3rem; background: #fff; color: #212529; font: 15px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
main { max-width: 36rem; margin: 0 auto; }
h1 { font-size: 1.5rem; margin: 0 0 1rem; }
label { display: block; font-weight: 600; margin: .75rem 0 .25rem; }
input[type=text], input[type=email] { box-sizing: border-box; width: 100%; padding: .5rem .75rem; font: inherit; border: 1px solid #ced4da; border-radius: .375rem; }
button { margin-top: 1rem; padding: .5rem 1.25rem; font: inherit; color: #fff; background: #0d6efd; border: 0; border-radius: .375rem; cursor: pointer; }
.pg-track-note, .pg-track-list li { padding: 1rem; border: 1px solid #dee2e6; border-radius: .375rem; margin: 0 0 1rem; }
.pg-track-error { color: #842029; background: #f8d7da; border-color: #f5c2c7; }
.pg-track-list { list-style: none; margin: 0 0 1.5rem; padding: 0; }
.pg-track-head { display: flex; justify-content: space-between; gap: 1rem; }
.pg-track-status { color: #495057; }
.pg-track-dates { color: #495057; font-size: .9rem; }
</style>
</head>
<body>
<main>
<h1>' . h($title) . '</h1>
' . $answer . '
<form method="post" action="">
' . get_token_field() . '
<label for="order_number">' . h(lang('Order Number')) . '</label>
<input type="text" id="order_number" name="order_number" value="' . h($number) . '" inputmode="numeric" autocomplete="off" required maxlength="40">
<label for="email_address">' . h(lang('Billing E-mail Address')) . '</label>
<input type="email" id="email_address" name="email_address" value="' . h($email) . '" autocomplete="email" required maxlength="100">
<button type="submit">' . h(lang('Track')) . '</button>
</form>
</main>
</body>
</html>';
}
