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
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

include('init.php');

// Answered in JSON before validate_user(), which sends a signed-out request
// to the sign-in page.
if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN) {
    list($image_editor_payload, $image_editor_status) = pg_image_editor_refusal(lang('Your session has expired. Reload the page and sign in again.'), 401, 'session');
} else {
    $image_editor_body = (string) file_get_contents('php://input');
    $image_editor_user = validate_user();

    // A file a team chat message carries opens only for a side of that
    // conversation or a role-0 administrator, as in get_file.php: the
    // folder's edit right would otherwise read its pixels or save a copy
    // anyone could open. The request is read as pg_image_editor_api() reads
    // it: the whole body, or its first line when a file's bytes follow.
    $image_editor_request = json_decode($image_editor_body, true);

    if (!is_array($image_editor_request)) {
        list($image_editor_line) = pg_image_editor_binary_body($image_editor_body);
        $image_editor_request = json_decode($image_editor_line, true);
        unset($image_editor_line);
    }

    $image_editor_files = pg_image_editor_request_files($image_editor_request);
    unset($image_editor_request);

    if (pg_chat_attachment_files_refused(pg_chat_attachment_conversations_by_file($image_editor_files['ids'], $image_editor_files['names']), (int) $image_editor_user['id'], (int) $image_editor_user['role'])) {
        log_activity(pg_log_lang(array('string' => 'access denied to file attachment ({var:1})', 'vars' => array(implode(', ', array_merge($image_editor_files['ids'], $image_editor_files['names']))))), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
        list($image_editor_payload, $image_editor_status) = pg_image_editor_refusal(lang('Access denied'), 403);
    } else {
        list($image_editor_payload, $image_editor_status) = pg_image_editor_api($image_editor_body, $image_editor_user);
    }
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

// Pixels made for the request and not kept (action base) are sent as they
// are too.
if (isset($image_editor_payload['data'], $image_editor_payload['type'])) {
    header('Content-Type: ' . $image_editor_payload['type']);
    header('Content-Length: ' . strlen($image_editor_payload['data']));
    header('X-Content-Type-Options: nosniff');
    echo $image_editor_payload['data'];
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo encode_json($image_editor_payload);
