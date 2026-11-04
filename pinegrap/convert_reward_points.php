<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A signed-in customer turns reward points into store credit from My
 * Account (pg_credit_points_convert(), includes/fn/store_credit_points.php).
 *
 * POST only, with the form token: points (a whole number) and send_to (a
 * path on this site, the My Account page without one). Staff placing an
 * order on someone's behalf (ghost) cannot convert that customer's points.
 * The customer is sent back with a notice or an error for the my_account
 * form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    output_error(h(lang('Method not allowed.')), 405);
}

validate_token_field();

pg_request_single_values(array('points', 'send_to'));

$points_user_id = pg_credit_checkout_user();

if ($points_user_id <= 0) {
    output_error(h(lang('You must be logged in to view this page.')), 403);
}

$points_account_url = (string) get_page_type_url('my account');
$points_send_to = pg_safe_redirect_path($_POST['send_to'] ?? '', ($points_account_url !== '') ? $points_account_url : '');

$points_result = pg_credit_points_convert($points_user_id, trim((string) ($_POST['points'] ?? '')));

include_once('liveform.class.php');
$points_notices = new liveform('my_account');

if ($points_result['error'] === '') {
    // The symbol is stored as HTML (&#8378;); the sentence is escaped once.
    $points_symbol = html_entity_decode((string) BASE_CURRENCY_SYMBOL, ENT_QUOTES, 'UTF-8');
    $points_notices->add_notice(h(lang(array(
        'string' => '{var:1} reward points were added to your store credit as {var:2}.',
        'vars'   => array(pg_format_number($points_result['points'], 0), pg_format_money($points_result['value'] / 100, $points_symbol)),
    ))));
} else {
    $points_notices->add_error(h(pg_credit_points_error_text($points_result['error'])));
}

go($points_send_to);
