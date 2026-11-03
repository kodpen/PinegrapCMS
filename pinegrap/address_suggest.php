<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Address suggestions for the address forms, from the site's provider
 * (includes/fn/address_autocomplete.php).
 *
 *   POST action=suggest, q, country, lang, token
 *        -> {"ok": true, "suggestions": [{"id": …, "label": …}, …]}
 *   POST action=fill, id, lang, token
 *        -> {"ok": true, "boxes": {address_1, address_2, city, state,
 *            state_code, zip_code, country}}
 *
 * At most PG_ADDR_SUGGESTIONS suggestions; a fill takes only an id this
 * session was given. A provider that fails or is not set up answers
 * {"ok": false, "suggestions": []} with 200, and the form keeps what was
 * typed. A request refused before it is read is {"error": <code>,
 * "message": <sentence>} with its status: method 405, request 400 or 413,
 * session 403 (the token), rate 429.
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
 * @param array $data
 * @param int   $status
 */
function address_suggest_answer($data, $status = 200)
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

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    address_suggest_answer(array('error' => 'method', 'message' => lang('Method not allowed.')), 405);
}

if (pg_post_body_discarded()) {
    address_suggest_answer(array('error' => 'request', 'message' => lang('The request was too large.')), 413);
}

pg_request_single_values(array('action', 'q', 'country', 'lang', 'id', 'token'));

// The form token, compared here: validate_token_field() answers in HTML.
// Without it any page could spend the site's provider calls.
$session_token = (string) ($_SESSION['software']['token'] ?? '');
$post_token = (string) ($_POST['token'] ?? '');

if (($session_token === '') || !hash_equals($session_token, $post_token)) {
    address_suggest_answer(array('error' => 'session', 'message' => lang('Your session has expired. Reload the page and try again.')), 403);
}

$action = (string) ($_POST['action'] ?? '');

if (!in_array($action, array('suggest', 'fill'), true)) {
    address_suggest_answer(array('error' => 'request', 'message' => lang('Invalid request.')), 400);
}

if (pg_addr_throttled()) {
    header('Retry-After: ' . PG_ADDR_RATE_WINDOW);
    address_suggest_answer(array('error' => 'rate', 'message' => lang('Too many requests from your address. Please wait a moment and try again.')), 429);
}

try {
    if ($action === 'suggest') {
        $result = pg_addr_suggest((string) ($_POST['q'] ?? ''), (string) ($_POST['country'] ?? ''), (string) ($_POST['lang'] ?? ''));

        address_suggest_answer(array('ok' => $result['ok'], 'suggestions' => $result['items']));
    }

    $result = pg_addr_fill((string) ($_POST['id'] ?? ''), (string) ($_POST['lang'] ?? ''));

    address_suggest_answer(array('ok' => $result['ok'], 'boxes' => $result['boxes']));
} catch (Throwable $error) {
    address_suggest_answer(array('ok' => false, 'suggestions' => array()));
}
