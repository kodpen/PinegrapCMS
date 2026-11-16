<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Coordinates for a collection's places (includes/fn/place_map.php), asked
 * by the panel's buttons only.
 *
 *   POST action=lookup, page_id, values[<field id>], token
 *        -> {"ok": true, "state": "found", "lat": …, "lng": …, "message": …}
 *        Looks up the address as it stands in the record's form; writes
 *        nothing, the form fills the two fields and the user saves.
 *   POST action=bulk, page_id, token
 *        -> {"ok": true, "found": n, "not_found": [{reference, address}],
 *            "left": n, "state": …, "message": …}
 *        Looks up the next PG_PLACE_MAP_BULK records of the form that have
 *        an address and lack a coordinate, and writes what is found. A
 *        record found nothing for is not asked again in this session.
 *
 * Refused: method 405, session 403 (the token), access 403 (the record
 * screens' rule: Manager and above, or the forms right on the form's
 * folder), form 404 (no place fields).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

/**
 * Sends the answer and ends the request.
 *
 * @param array $data
 * @param int   $status
 */
function place_coordinates_answer($data, $status = 200)
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
    place_coordinates_answer(array('ok' => false, 'message' => lang('Method not allowed.')), 405);
}

$user = validate_user();

pg_request_single_values(array('action', 'page_id', 'token'));

$session_token = (string) ($_SESSION['software']['token'] ?? '');

if (($session_token === '') || !hash_equals($session_token, (string) ($_POST['token'] ?? ''))) {
    place_coordinates_answer(array('ok' => false, 'message' => lang('Your session expired. Reload the page and try again.')), 403);
}

$page_id = (int) ($_POST['page_id'] ?? 0);
$folder_id = (int) db_value("SELECT page_folder FROM page WHERE page_id = '" . $page_id . "'");

if (!pg_place_map_may($user, ($folder_id > 0) && check_edit_access($folder_id))) {
    log_activity(lang('access denied to forms'), $_SESSION['sessionusername']);
    place_coordinates_answer(array('ok' => false, 'message' => lang('Access denied.')), 403);
}

$roles = pg_place_map_fields($page_id);

if ($roles === null) {
    place_coordinates_answer(array('ok' => false, 'message' => lang('This form has no address, latitude and longitude fields.')), 404);
}

if (($_POST['action'] ?? '') === 'bulk') {
    $skip = $_SESSION['software']['place_map_skip'][$page_id] ?? array();
    $skip = is_array($skip) ? $skip : array();
    $summary = pg_place_map_bulk($page_id, $roles, $skip, (int) $user['id']);

    foreach ($summary['not_found'] as $missing) {
        $skip[] = (int) $missing['id'];
    }

    $_SESSION['software']['place_map_skip'][$page_id] = array_slice(array_values(array_unique($skip)), -1000);

    if ($summary['found'] > 0) {
        log_activity(lang(array('string' => 'coordinates were found for {var:1} submitted forms', 'vars' => array((string) $summary['found']))), $_SESSION['sessionusername']);
    }

    place_coordinates_answer(array(
        'ok'        => true,
        'found'     => $summary['found'],
        'not_found' => array_map(function ($missing) {
            return array('reference' => $missing['reference'], 'address' => $missing['address']);
        }, $summary['not_found']),
        'left'      => $summary['left'],
        'state'     => $summary['state'],
        'message'   => pg_place_map_bulk_message($summary),
    ));
}

$values = (isset($_POST['values']) && is_array($_POST['values'])) ? $_POST['values'] : array();
$result = pg_place_map_lookup(pg_place_map_address_text($values, $roles['address']));

place_coordinates_answer(array(
    'ok'      => true,
    'state'   => $result['state'],
    'lat'     => $result['lat'],
    'lng'     => $result['lng'],
    'message' => pg_place_map_message($result),
));
