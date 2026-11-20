<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The places of a "Map of places" block (includes/fn/place_list.php),
 * asked by assets/js/pg_map.js on the page that holds the block.
 *
 *   GET page=<page id>
 *     -> {"ok": true, "places": [{name, address, hours, phone, lat, lng,
 *        url}], "cut": bool, "note": ""}
 *
 * The request carries the page id only; which records and which fields the
 * answer holds is decided on the server: the records the page's form list
 * shows a visitor, at most PG_PLACE_LIST_MAX, the fields by name. "note" is
 * the line a designer sees (no list on the page, fields missing, a cut
 * list); a visitor gets ''.
 *
 *   POST find=<typed address>
 *     -> {"ok": true, "state": "found"|"not_found"|"empty"|"wait"|"error",
 *        "lat": number|null, "lng": number|null}
 *
 * The lookup (X12) asks Photon once per press of Find, with its own lower
 * rate. The text comes in the body, so it is not in the address a server
 * log writes; it is neither stored nor logged and does not come back.
 *
 * Refused: method 405, a cross-site request 403, rate 429.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/place_list.php');

/**
 * Sends the answer and ends the request.
 *
 * @param array $data
 * @param int   $status
 */
function places_answer($data, $status = 200)
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

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));

if (!in_array($method, array('GET', 'HEAD', 'POST'), true)) {
    header('Allow: GET, HEAD, POST');
    places_answer(array('ok' => false, 'message' => lang('Method not allowed.')), 405);
}

// Same origin only: the page that holds the block asks.
if (pg_request_cross_site($_SERVER)) {
    places_answer(array('ok' => false, 'message' => lang('Access denied.')), 403);
}

// A typed address: one Photon lookup, nothing kept.
if ($method === 'POST') {
    if (!pg_place_list_find_on()) {
        places_answer(array('ok' => false, 'message' => lang('Access denied.')), 403);
    }

    if (pg_place_list_find_throttled()) {
        header('Retry-After: ' . PG_PLACE_LIST_RATE_WINDOW);
        places_answer(array('ok' => false, 'message' => lang('Too many requests. Please try again in a minute.')), 429);
    }

    places_answer(pg_place_list_find($_POST['find'] ?? ''));
}

if (pg_place_list_throttled()) {
    header('Retry-After: ' . PG_PLACE_LIST_RATE_WINDOW);
    places_answer(array('ok' => false, 'message' => lang('Too many requests. Please try again in a minute.')), 429);
}

pg_request_single_values(array('page'));

$answer = pg_place_list_for_page((int) ($_GET['page'] ?? 0));

// The reason is the designer's: a visitor sees the list or an empty one.
$designer = defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ROLE') && ((int) USER_ROLE < 3);

places_answer(array(
    'ok'     => true,
    'places' => $answer['places'],
    'cut'    => $answer['cut'],
    'note'   => $designer ? pg_place_list_reason_text($answer['reason']) : '',
));
