<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - open an account from the dialog of a document screen (invoice,
 * quote, delivery note, receipt, cheque, bank statement line) and answer with
 * its id and name, so the screen picks it without being left. POST only,
 * JSON in every answer, refusals included: the caller is a script, and an
 * HTML error page reaches it as "unexpected token <".
 *
 * The work is erp_account_quick_create() (includes/erp/account_quick.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

/**
 * Send one JSON answer and stop.
 *
 * @param array $data
 * @param int   $status
 */
function erp_account_quick_json($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit();
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    erp_account_quick_json(array('ok' => false, 'code' => 'method', 'message' => lang('Access denied.')), 405);
}

if (pg_post_body_discarded()) {
    erp_account_quick_json(array('ok' => false, 'code' => 'too_large', 'message' => lang('The request was larger than this server accepts.')), 413);
}

if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN) {
    erp_account_quick_json(array('ok' => false, 'code' => 'session', 'message' => lang('Your session has expired. Reload the page and sign in again.')), 401);
}

$user = validate_user();

// The conditions of validate_erp_access($user) for a POST, answered in JSON:
// that function prints an HTML page.
if (!defined('ERP_ENABLED') || !ERP_ENABLED) {
    erp_account_quick_json(array('ok' => false, 'code' => 'disabled', 'message' => lang('The ERP module is not switched on.')), 403);
}

if (!(((int) $user['role'] < 3) || !empty($user['manage_erp']))) {
    log_activity(lang('access denied to erp'), $_SESSION['sessionusername'] ?? '');
    erp_account_quick_json(array('ok' => false, 'code' => 'denied', 'message' => lang('Access denied.')), 403);
}

// The accountant's right reads the ERP and changes nothing in it.
if ((((int) $user['role'] >= 3) && !empty($user['manage_erp_readonly'])) || (defined('USER_ERP_READONLY') && USER_ERP_READONLY)) {
    log_activity(lang('access denied to erp'), $_SESSION['sessionusername'] ?? '');
    erp_account_quick_json(array('ok' => false, 'code' => 'denied', 'message' => lang('Your account can read the ERP but not change anything in it.')), 403);
}

// validate_token_field() answers with an HTML page, so the same check by hand.
$session_token = (string) ($_SESSION['software']['token'] ?? '');
$post_token = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';

if (($session_token === '') || !hash_equals($session_token, $post_token)) {
    erp_account_quick_json(array('ok' => false, 'code' => 'session', 'message' => lang('Your session has expired. Reload the page and sign in again.')), 403);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

$created = erp_account_quick_create($user, $_POST);

if (!$created['success']) {
    erp_account_quick_json(array(
        'ok' => false,
        'code' => 'invalid',
        'message' => $created['error'],
        'field_errors' => !empty($created['field_errors']) ? $created['field_errors'] : new stdClass(),
    ), 422);
}

erp_account_quick_json(array(
    'ok' => true,
    'id' => (int) $created['id'],
    'title' => $created['title'],
    'kind' => $created['kind'],
    'message' => lang(array('string' => 'Account “{var:1}” opened and picked.', 'vars' => array($created['title']))),
    'field_errors' => new stdClass(),
));
