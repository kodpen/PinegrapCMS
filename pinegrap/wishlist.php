<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor's favourites, for the heart buttons of the catalog
 * (includes/fn/wishlist.php): the list of the signed-in account, or of the
 * visitor whose pg_wishlist cookie it is.
 *
 *   GET  ?action=state  {"ids": [...], "token": "...", "signed_in": bool}:
 *                       the products in the list, newest first, and the
 *                       session's form token. A catalog page may come from
 *                       the page cache, drawn for nobody: no token and every
 *                       heart empty. Its script asks here, and this answer is
 *                       never cached.
 *   POST action=add | remove | toggle, product_id, token
 *                       {"product_id": N, "on": bool}: the product's place in
 *                       the list after the change.
 *
 * A POST sent with X-Requested-With: XMLHttpRequest is answered in JSON; any
 * other POST (a form, without a script) is sent back to its send_to field, a
 * path on the site (the home page without one). A refusal is {"error": <code>, "message": <sentence>}
 * with its status: method 405, request 400 or 413, action 400, session 403
 * (the token), rate 429, product 404 (not shown in the catalog), unavailable
 * 503 (no table yet), failed 500.
 *
 * Each read and write first moves a visitor's cookie list into the account
 * of a visitor who has signed in since (pg_wishlist_merge()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$wishlist_method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));

// The state is read by a script; a write by a script says so in its header.
$wishlist_json = ($wishlist_method !== 'POST')
    || (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

// From here on a failed query of a script's request is a JSON error, never
// the HTML error screen and never the database's own words.
if ($wishlist_json) {
    pg_error_throws(true);
}

/**
 * Sends one JSON answer and stops.
 *
 * @param array $data
 * @param int   $status
 */
function wishlist_answer($data, $status = 200)
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
function wishlist_refuse($error, $message, $status)
{
    if ($GLOBALS['wishlist_json']) {
        wishlist_answer(array('error' => $error, 'message' => $message), $status);
    }

    // output_error() reads a 404 as an address nobody answers (short links,
    // the 404 log); a product refused here is not one.
    output_error(h($message), ($status === 404) ? 400 : $status);
}

// A body over post_max_size reaches PHP as an empty $_POST; without this it
// would read as a missing token.
if (pg_post_body_discarded()) {
    wishlist_refuse('request', lang('The request was too large.'), 413);
}

pg_request_single_values(array('action', 'product_id', 'token'));

if (($wishlist_method === 'GET') || ($wishlist_method === 'HEAD')) {

    $action = pg_wishlist_action($_GET['action'] ?? '');

    if ($action === 'state') {

        try {
            pg_wishlist_merge();
            $ids = pg_wishlist_ids();
        } catch (Throwable $error) {
            wishlist_refuse('failed', lang('Something went wrong. Please try again.'), 500);
        }

        wishlist_answer(array(
            'ids'       => $ids,
            'token'     => (string) ($_SESSION['software']['token'] ?? ''),
            'signed_in' => (pg_wishlist_user_id() > 0),
        ));
    }

    // A write never travels in a link.
    if ($action !== '') {
        header('Allow: POST');
        wishlist_refuse('method', lang('Method not allowed.'), 405);
    }

    wishlist_refuse('action', lang('Unknown action.'), 400);
}

if ($wishlist_method !== 'POST') {
    header('Allow: GET, POST');
    wishlist_refuse('method', lang('Method not allowed.'), 405);
}

// The token check of validate_token_field(), answered the way the request
// was asked: that function always prints an HTML page. Without it a page on
// another site could fill or empty the visitor's list.
$session_token = (string) ($_SESSION['software']['token'] ?? '');
$post_token = (string) ($_POST['token'] ?? '');

if (($session_token === '') || !hash_equals($session_token, $post_token)) {
    wishlist_refuse('session', lang('Your session has expired. Reload the page and try again.'), 403);
}

$action = pg_wishlist_action($_POST['action'] ?? '');

if (!in_array($action, array('add', 'remove', 'toggle'), true)) {
    wishlist_refuse('action', lang('Unknown action.'), 400);
}

$product_id = pg_wishlist_product_id($_POST['product_id'] ?? '');

if ($product_id <= 0) {
    wishlist_refuse('request', lang('Invalid request.'), 400);
}

if (!pg_wishlist_ready()) {
    wishlist_refuse('unavailable', lang('Favourites are not available on this site yet.'), 503);
}

// Counted once the request is known to come from the site's own page.
if (pg_wishlist_write_throttled()) {
    header('Retry-After: ' . PG_WISHLIST_WRITE_WINDOW);
    wishlist_refuse('rate', lang('Too many requests from your address. Please wait a moment and try again.'), 429);
}

try {
    pg_wishlist_merge();

    $on = pg_wishlist_new_state($action, ($action === 'toggle') ? pg_wishlist_has($product_id) : false);
    $state = pg_wishlist_set($product_id, $on);

    // Only adding is refused once the request is checked: for a product the
    // catalog does not show, or when a visitor's cookie could not be sent.
    $shown = ($state !== null) || pg_wishlist_product_ok($product_id);
} catch (Throwable $error) {
    wishlist_refuse('failed', lang('Something went wrong. Please try again.'), 500);
}

if (!$shown) {
    wishlist_refuse('product', lang('Sorry, this product is not available right now.'), 404);
}

if ($state === null) {
    wishlist_refuse('failed', lang('Something went wrong. Please try again.'), 500);
}

if ($wishlist_json) {
    wishlist_answer(array('product_id' => $product_id, 'on' => $state));
}

// A form without a script goes back to the page it was sent from.
session_write_close();

header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($_POST['send_to'] ?? ''));
exit();
