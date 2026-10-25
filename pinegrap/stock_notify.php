<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A request to be told when an out-of-stock product is back
 * (includes/fn/stock_notify.php).
 *
 *   POST product_id, email_address, token, send_to
 *
 * email_address may be left empty by a signed-in visitor: the account's
 * address is used then. A POST sent with X-Requested-With: XMLHttpRequest is
 * answered in JSON, {"status": <code>, "ok": bool, "message": <sentence>};
 * any other POST is sent back to its send_to field (a path on the site, the
 * home page without one), with the sentence among the product page's
 * messages (the catalog_detail form). A request refused before it is read is
 * {"error": <code>, "message": <sentence>} with its status: method 405,
 * request 400 or 413, session 403 (the token), rate 429, failed 500.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$stock_notify_json = (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

// From here on a failed query of a script's request is a JSON error, never
// the HTML error screen and never the database's own words.
if ($stock_notify_json) {
    pg_error_throws(true);
}

/**
 * Sends one JSON answer and stops.
 *
 * @param array $data
 * @param int   $status
 */
function stock_notify_answer($data, $status = 200)
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex');
    }

    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Refuses the request: in JSON for a script, as the error screen for a form.
 *
 * @param string $error   the code a script reads
 * @param string $message the sentence the visitor reads
 * @param int    $status
 */
function stock_notify_refuse($error, $message, $status)
{
    if ($GLOBALS['stock_notify_json']) {
        stock_notify_answer(array('error' => $error, 'message' => $message), $status);
    }

    output_error(h($message), $status);
}

// A body over post_max_size reaches PHP as an empty $_POST; without this it
// would read as a missing token.
if (pg_post_body_discarded()) {
    stock_notify_refuse('request', lang('The request was too large.'), 413);
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    stock_notify_refuse('method', lang('Method not allowed.'), 405);
}

pg_request_single_values(array('product_id', 'email_address', 'token', 'send_to'));

// The token check of validate_token_field(), answered the way the request
// was asked: that function always prints an HTML page. Without it a page on
// another site could sign anybody's address up.
$session_token = (string) ($_SESSION['software']['token'] ?? '');
$post_token = (string) ($_POST['token'] ?? '');

if (($session_token === '') || !hash_equals($session_token, $post_token)) {
    stock_notify_refuse('session', lang('Your session has expired. Reload the page and try again.'), 403);
}

$product_id = (int) ($_POST['product_id'] ?? 0);

if ($product_id <= 0) {
    stock_notify_refuse('request', lang('Invalid request.'), 400);
}

// Counted once the request is known to come from the site's own page.
if (pg_stock_notify_throttled()) {
    header('Retry-After: ' . PG_STOCK_NOTIFY_WRITE_WINDOW);
    stock_notify_refuse('rate', lang('Too many requests from your address. Please wait a moment and try again.'), 429);
}

$user_id = (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ID')) ? (int) USER_ID : 0;

try {
    $status = pg_stock_notify_request($product_id, (string) ($_POST['email_address'] ?? ''), $user_id);
} catch (Throwable $error) {
    stock_notify_refuse('failed', lang('Something went wrong. Please try again.'), 500);
}

$ok = pg_stock_notify_status_ok($status);
$message = pg_stock_notify_status_message($status);

if ($stock_notify_json) {
    stock_notify_answer(array('status' => $status, 'ok' => $ok, 'message' => $message));
}

// A form goes back to the page it was sent from, the sentence among the
// product page's messages.
include_once('liveform.class.php');
$liveform = new liveform('catalog_detail');

if ($ok) {
    $liveform->add_notice(h($message));
} else {
    $liveform->mark_error('email_address', h($message));
}

session_write_close();

header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($_POST['send_to'] ?? ''));
exit();
