<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The picker's endpoint (includes/fn/picker.php). Every request names a kind
 * of pg_picker_kinds() and one request:
 *
 *   GET ?kind=<name>&request=list&folder=<id>[&page=N&per_page=N]
 *       {kind, request, folder, items, page, per_page, more}
 *   GET ?kind=<name>&request=search&q=<text>[&page=N&per_page=N]
 *       {kind, request, items, page, per_page, more}
 *   GET ?kind=<name>&request=tree[&node_ids[]=<id>...]   (0 is the top)
 *       {kind, request, nodes: [{id, rows}]}
 *   GET ?kind=<name>&request=resolve&values[]=<stored value>...
 *       {kind, request, items: [{value, text, state, ...}]}
 *
 * An item is {id, value, text, type, folder}; value is what a pick stores.
 * The kind decides the types, the public condition and what is stored; the
 * browser never sends a condition. The Recycle Bin and attachments are always
 * left out, and a role 3 user is kept to the folders they may edit.
 *
 * Read-only, so no CSRF token: a forged request learns names, and only with
 * the victim's session, which a page of another site cannot read back. Every
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

// Read only: the session lock is released so the dialog's requests run side
// by side.
if (function_exists('session_status') && (session_status() === PHP_SESSION_ACTIVE)) {
    session_write_close();
}

pg_picker_respond(pg_picker_answer($request, $user));
