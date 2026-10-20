<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - ask GİB, through the active e-document provider, about the tax
 * number typed on an account form, and answer with whether it is an
 * e-Invoice user, its mailboxes and the name it is registered under. The
 * form offers that name for the account's name field; nothing here writes
 * the name.
 *
 * With an account id the question is the card's own
 * (erp_edoc_account_check_taxpayer()): the answer is kept on the card when
 * the number is the one the card holds. Without one (a new account) nothing
 * is kept (erp_edoc_taxpayer_lookup()).
 *
 * POST only, JSON in every answer, refusals included: the caller is a
 * script, and an HTML error page reaches it as "unexpected token <".
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
function erp_taxpayer_lookup_json($data, $status = 200)
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
    erp_taxpayer_lookup_json(array('ok' => false, 'code' => 'method', 'message' => lang('Access denied.')), 405);
}

if (pg_post_body_discarded()) {
    erp_taxpayer_lookup_json(array('ok' => false, 'code' => 'too_large', 'message' => lang('The request was larger than this server accepts.')), 413);
}

if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN) {
    erp_taxpayer_lookup_json(array('ok' => false, 'code' => 'session', 'message' => lang('Your session has expired. Reload the page and sign in again.')), 401);
}

$user = validate_user();

// The conditions of validate_erp_access($user) for a POST, answered in JSON:
// that function prints an HTML page.
if (!defined('ERP_ENABLED') || !ERP_ENABLED) {
    erp_taxpayer_lookup_json(array('ok' => false, 'code' => 'disabled', 'message' => lang('The ERP module is not switched on.')), 403);
}

if (!(((int) $user['role'] < 3) || !empty($user['manage_erp']))) {
    log_activity(lang('access denied to erp'), $_SESSION['sessionusername'] ?? '');
    erp_taxpayer_lookup_json(array('ok' => false, 'code' => 'denied', 'message' => lang('Access denied.')), 403);
}

// The accountant's right reads the ERP and changes nothing in it; the
// question writes GİB's answer onto the card.
if ((((int) $user['role'] >= 3) && !empty($user['manage_erp_readonly'])) || (defined('USER_ERP_READONLY') && USER_ERP_READONLY)) {
    log_activity(lang('access denied to erp'), $_SESSION['sessionusername'] ?? '');
    erp_taxpayer_lookup_json(array('ok' => false, 'code' => 'denied', 'message' => lang('Your account can read the ERP but not change anything in it.')), 403);
}

// validate_token_field() answers with an HTML page, so the same check by hand.
$session_token = (string) ($_SESSION['software']['token'] ?? '');
$post_token = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';

if (($session_token === '') || !hash_equals($session_token, $post_token)) {
    erp_taxpayer_lookup_json(array('ok' => false, 'code' => 'session', 'message' => lang('Your session has expired. Reload the page and sign in again.')), 403);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/account_form.php');

// One value per field: an array read through (string) would be asked about
// as "Array".
pg_request_single_values(array('tax_number', 'account_id'));

if (!erp_edoc_installed() || !erp_edoc_supports('check_taxpayer')) {
    erp_taxpayer_lookup_json(array('ok' => false, 'code' => 'unsupported', 'message' => lang('No e-document provider is selected on the E-Invoice card of the commerce settings.')), 409);
}

$tax_number = (string) ($_POST['tax_number'] ?? '');
$account_id = (int) ($_POST['account_id'] ?? 0);

if ($account_id > 0) {
    $answer = erp_edoc_account_check_taxpayer($account_id, $tax_number);
} else {
    $answer = erp_edoc_taxpayer_lookup($tax_number);
    $answer['stored'] = false;
}

if (empty($answer['success'])) {
    erp_taxpayer_lookup_json(array('ok' => false, 'code' => 'refused', 'message' => (string) $answer['error']), 422);
}

// The line under the number says what the card now holds; only redrawn
// when the answer was kept there.
$state = '';

if (!empty($answer['stored'])) {
    $state = erp_account_form_einvoice_state(erp_account($account_id));
}

erp_taxpayer_lookup_json(array(
    'ok' => true,
    'message' => (string) $answer['message'],
    'title' => mb_substr((string) ($answer['title'] ?? ''), 0, 255),
    'stored' => !empty($answer['stored']),
    'state' => $state,
));
