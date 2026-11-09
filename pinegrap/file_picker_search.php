<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Search of the panel's file and image pickers (includes/fn/file_picker_search.php):
 *
 *   GET ?kind=<name>&q=<text>   {results: [{id, text}]}, at most 30
 *
 * The kind names a filter of pg_file_picker_kinds(): which types, whether
 * only files every visitor may open, whether the answer posts the name or
 * the id. The Recycle Bin, attachments and, for a role 3 user, the folders
 * they may not edit are always left out.
 *
 * Read-only, so no CSRF token: a forged request learns file names, and only
 * with the victim's session, which a page of another site cannot read back.
 * Every answer is JSON, a refusal too: the caller is a fetch() that shows the
 * error text in the picker.
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
function pg_file_picker_respond($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit();
}

// validate_user() would send a browser without a session to the sign-in
// page, which a fetch() reads as broken JSON.
if (!defined('USER_LOGGED_IN') || (USER_LOGGED_IN !== true)) {
    pg_file_picker_respond(array('results' => array(), 'error' => lang('Your session has expired. Reload the page and sign in again.')), 403);
}

$user = validate_user();

$kind = (isset($_GET['kind']) && is_string($_GET['kind'])) ? $_GET['kind'] : '';
$query = (isset($_GET['q']) && is_string($_GET['q'])) ? $_GET['q'] : '';

$verdict = pg_file_picker_access_verdict($kind, $user['role'], ((int) $user['role'] >= 3) && no_acl_check($user['id']));

if ($verdict === 'unknown') {
    pg_file_picker_respond(array('results' => array(), 'error' => lang('Page not found.')), 404);
}

if ($verdict !== 'ok') {
    log_activity(lang('access denied because user attempted to access area for higher role'), $_SESSION['sessionusername'] ?? '');
    pg_file_picker_respond(array('results' => array(), 'error' => lang('Access denied')), 403);
}

pg_file_picker_respond(array('results' => pg_file_picker_search($kind, $query, $user)));
