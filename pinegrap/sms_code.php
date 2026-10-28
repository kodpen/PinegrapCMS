<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Texts a one-time code (includes/fn/sms_codes.php).
 *
 *   POST purpose, token, phone
 *
 * purpose 'cod': the code that confirms the visitor's cash-on-delivery
 * order, sent to phone (the billing phone number typed on the checkout form)
 * or, without one, to the order's billing phone number. submit_order.php
 * checks it against the number the order is placed with, so a number changed
 * after the code was sent needs a new code.
 *
 * The answer is JSON, {"ok": bool, "error": <code>, "message": <sentence>}:
 * method 405, request 400 or 413, session 403 (the token), rate 429, off 409,
 * number 400, send 503, failed 500.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

pg_error_throws(true);

/**
 * Sends one JSON answer and stops.
 *
 * @param string $error   '' when the code was sent
 * @param string $message
 * @param int    $status
 */
function sms_code_answer($error, $message, $status = 200)
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex');
    }

    echo json_encode(array('ok' => ($error === ''), 'error' => $error, 'message' => $message), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

// A body over post_max_size reaches PHP as an empty $_POST; without this it
// would read as a missing token.
if (pg_post_body_discarded()) {
    sms_code_answer('request', lang('The request was too large.'), 413);
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    sms_code_answer('method', lang('Method not allowed.'), 405);
}

pg_request_single_values(array('purpose', 'token', 'phone'));

if (!pg_sms_code_token_ok($_SESSION['software']['token'] ?? '', $_POST['token'] ?? '')) {
    sms_code_answer('session', lang('Your session has expired. Reload the page and try again.'), 403);
}

if ((string) ($_POST['purpose'] ?? '') !== 'cod') {
    sms_code_answer('request', lang('Invalid request.'), 400);
}

$order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);

try {
    $order = ($order_id > 0) ? db_item("SELECT id, billing_phone_number FROM orders WHERE (id = '" . $order_id . "') AND (status = 'incomplete')") : null;

    if (!$order) {
        sms_code_answer('request', lang('Invalid request.'), 400);
    }

    $phone = trim((string) ($_POST['phone'] ?? ''));
    $sent = pg_sms_code_send('cod', 'order:' . $order_id, ($phone !== '') ? $phone : (string) $order['billing_phone_number']);
} catch (Throwable $error) {
    sms_code_answer('failed', lang('Something went wrong. Please try again.'), 500);
}

$statuses = array('' => 200, 'off' => 409, 'number' => 400, 'limit' => 429, 'send' => 503);

session_write_close();

sms_code_answer($sent['error'], pg_sms_code_sentence($sent['error'], $sent['masked']), $statuses[$sent['error']]);
