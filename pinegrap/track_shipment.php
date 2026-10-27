<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Guest parcel lookup (includes/fn/shipment_lookup.php): a visitor without
 * an account types an order number and the order's billing e-mail and sees
 * the order's parcels - carrier, tracking number and link, status and dates.
 *
 *   GET          the form
 *   POST         order_number, email_address, token
 *
 * A wrong number and a wrong address give the same answer. Lookups are
 * counted per visitor address: PG_SHIPMENT_LOOKUP_LIMIT an hour, then 429.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
    output_error(lang('This page is not available.'), 404);
}

// The answer carries an order's parcels: never kept by a cache.
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Content-Type: text/html; charset=utf-8');

initialize_token();

$number_typed = '';
$email_typed = '';
$answer = '';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') {

    // A body over post_max_size reaches PHP as an empty $_POST; without this
    // it would read as a missing token.
    if (pg_post_body_discarded()) {
        output_error(lang('The request was too large.'), 413);
    }

    pg_request_single_values(array('order_number', 'email_address', 'token'));

    $number_typed = mb_substr(trim((string) ($_POST['order_number'] ?? '')), 0, 40, 'UTF-8');
    $email_typed = mb_substr(trim((string) ($_POST['email_address'] ?? '')), 0, 100, 'UTF-8');

    $session_token = (string) ($_SESSION['software']['token'] ?? '');
    $post_token = (string) ($_POST['token'] ?? '');

    if (($session_token === '') || !hash_equals($session_token, $post_token)) {
        http_response_code(403);
        $answer = '<p class="pg-track-note pg-track-error">' . h(lang('Your session has expired. Reload the page and try again.')) . '</p>';

    // Counted once the request is known to come from this page.
    } elseif (pg_shipment_lookup_throttled()) {
        http_response_code(429);
        header('Retry-After: ' . PG_SHIPMENT_LOOKUP_WINDOW);
        $answer = '<p class="pg-track-note pg-track-error">' . h(lang('Too many requests from your address. Please wait a moment and try again.')) . '</p>';

    } else {
        $parcels = pg_shipment_lookup(pg_shipment_lookup_order_number($number_typed), pg_shipment_lookup_email($email_typed));

        $answer = ($parcels === null)
            ? '<p class="pg-track-note pg-track-error">' . h(lang('No order was found with this order number and e-mail address.')) . '</p>'
            : pg_shipment_lookup_result_html($parcels);
    }
}

session_write_close();

echo pg_shipment_lookup_page_html($number_typed, $email_typed, $answer, lang(array('info' => true)));
