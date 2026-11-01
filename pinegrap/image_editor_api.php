<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The image editor's server side (assets/js/pg_image_editor.js): opening a
 * file and saving an edit. A JSON body in, a JSON answer out, refusals
 * included; the work is in includes/fn/image_editor.php
 * (pg_image_editor_api()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// Answered in JSON before validate_user(), which sends a signed-out request
// to the sign-in page.
if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN) {
    list($image_editor_payload, $image_editor_status) = pg_image_editor_refusal(lang('Your session has expired. Reload the page and sign in again.'), 401, 'session');
} else {
    list($image_editor_payload, $image_editor_status) = pg_image_editor_api(file_get_contents('php://input'), validate_user());
}

header('Cache-Control: private, no-store');
http_response_code($image_editor_status);

// A version's picture (action picture) is sent as it is: the folder that
// holds them answers no URL.
if (isset($image_editor_payload['stream'])) {
    header('Content-Type: ' . $image_editor_payload['type']);
    header('Content-Length: ' . (int) filesize($image_editor_payload['stream']));
    header('X-Content-Type-Options: nosniff');
    readfile($image_editor_payload['stream']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo encode_json($image_editor_payload);
