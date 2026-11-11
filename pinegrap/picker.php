<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The picker's endpoint (includes/fn/picker.php). Every request names a kind
 * of pg_picker_kinds() and one request:
 *
 *   GET ?kind=<name>&request=list&folder=<id>[&page=N&per_page=N]
 *       {kind, request, folder, items, page, per_page, more, upload: {ok, reason}}
 *   GET ?kind=<name>&request=search&q=<text>[&page=N&per_page=N]
 *       {kind, request, items, page, per_page, more}
 *   GET ?kind=<name>&request=tree[&node_ids[]=<id>...]   (0 is the top)
 *       {kind, request, nodes: [{id, rows}]}
 *   GET ?kind=<name>&request=resolve&values[]=<stored value>...
 *       {kind, request, items: [{value, text, state, ...}]}
 *   POST ?kind=<name>&request=upload&folder=<id>   (token, file)
 *       {item} or {error}
 *
 * An item is {id, value, text, type, folder}; value is what a pick stores.
 * The kind decides the types, the public condition and what is stored; the
 * browser never sends a condition. The Recycle Bin and attachments are always
 * left out, and a role 3 user is kept to the folders they may edit.
 *
 * The reads take no CSRF token: a forged request learns names, and only with
 * the victim's session, which a page of another site cannot read back. The
 * upload request writes, so it is a POST with the session's token. Every
 * answer is JSON, a refusal too, with an error text the picker shows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');

/**
 * Answer and stop.
 *
 * @param array $payload
 * @param int   $status
 */
function pg_picker_respond($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit();
}

// validate_user() would send a browser without a session to the sign-in
// page, which a fetch() reads as broken JSON.
if (!defined('USER_LOGGED_IN') || (USER_LOGGED_IN !== true)) {
    pg_picker_respond(array('error' => lang('Your session has expired. Reload the page and sign in again.')), 403);
}

$user = validate_user();

$request = pg_picker_request($_GET);

if ($request['error'] === 'kind') {
    pg_picker_respond(array('error' => lang('Page not found.')), 404);
}

if ($request['error'] !== '') {
    pg_picker_respond(array('error' => lang('Invalid request.')), 400);
}

$verdict = pg_picker_access_verdict($request['kind'], $user['role'], ((int) $user['role'] >= 3) && no_acl_check($user['id']));

if ($verdict !== 'ok') {
    log_activity(lang('access denied because user attempted to access area for higher role'), $_SESSION['sessionusername'] ?? '');
    pg_picker_respond(array('error' => lang('Access denied')), 403);
}

// Upload is the one request that writes: a POST of one file with the
// session's token, into the folder the query names.
if ($request['request'] === 'upload') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        pg_picker_respond(array('error' => lang('Invalid request.')), 405);
    }

    // PHP threw the body away for being over post_max_size: the token and
    // the file are gone, and the reason is the size.
    if (pg_post_body_discarded()) {
        $limits = pg_upload_limits();
        pg_picker_respond(array('error' => pg_upload_body_discarded_message($limits['post_max'])), 413);
    }

    if (!pg_picker_token_valid($_SESSION['software']['token'] ?? '', $_POST['token'] ?? '')) {
        pg_picker_respond(array('error' => lang('Your session expired. Reload the page and try again.')), 403);
    }
}

// The session lock is released so the dialog's requests run side by side;
// nothing below writes to the session.
if (function_exists('session_status') && (session_status() === PHP_SESSION_ACTIVE)) {
    session_write_close();
}

if ($request['request'] === 'upload') {
    list($status, $payload) = pg_picker_upload_answer($request, $user, $_FILES['file'] ?? null);

    if ($status !== 200) {
        log_activity($payload['error'], $_SESSION['sessionusername'] ?? '');
    }

    pg_picker_respond($payload, $status);
}

pg_picker_respond(pg_picker_answer($request, $user));
