<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Passkeys for the browser's navigator.credentials (includes/fn/passkeys.php).
 * Every request is a POST that carries the session's token (field token) and
 * names its action; every answer is JSON:
 *
 *   register_options  creation options for a new passkey of the signed-in
 *                     account: {"ok": true, "options": {...}}
 *   register          the new credential (field credential: the JSON of
 *                     PublicKeyCredential.toJSON()) and its name (field name),
 *                     checked and kept: {"ok": true, "passkey": {...}}
 *   signin_options    request options for the account whose sign-in waits for
 *                     its second step; the answer goes to mfa.php, which
 *                     completes the sign-in
 *
 * A refusal is {"ok": false, "error": <code>, "message": <one sentence for
 * the visitor>} with its status. The codes of a refused credential stay in
 * the activity log; the message never tells them apart.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

/**
 * Sends one JSON answer and stops.
 *
 * @param array $data
 * @param int   $status
 */
function passkey_answer($data, $status = 200)
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

/**
 * Answers a refused ceremony: the code, and the sentence the visitor sees.
 *
 * @param string $error
 * @param string $context 'register' or 'signin'
 */
function passkey_refuse($error, $context)
{
    $status = 400;

    if ($error === 'unavailable') {
        $status = 503;
    } elseif (in_array($error, array('limit', 'duplicate', 'host'), true)) {
        $status = 409;
    } elseif ($error === 'storage') {
        $status = 500;
    }

    passkey_answer(array('ok' => false, 'error' => $error, 'message' => pg_passkey_refusal($error, $context)), $status);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    passkey_answer(array('ok' => false, 'error' => 'method', 'message' => lang('Method not allowed.')), 405);
}

// A body over post_max_size reaches PHP as an empty $_POST; without this it
// would read as a missing token.
if (pg_post_body_discarded()) {
    passkey_answer(array('ok' => false, 'error' => 'request', 'message' => lang('The request was too large.')), 413);
}

pg_request_single_values(array('action', 'token', 'credential', 'name'));

$action = (string) ($_POST['action'] ?? '');

// The token check of validate_token_field(), answered in JSON: that function
// prints an HTML page.
$session_token = (string) ($_SESSION['software']['token'] ?? '');
$post_token = (string) ($_POST['token'] ?? '');

if (($session_token === '') || !hash_equals($session_token, $post_token)) {
    passkey_answer(array('ok' => false, 'error' => 'session', 'message' => lang('Your session has expired. Reload the page and sign in again.')), 403);
}

switch ($action) {

    case 'register_options':
    case 'register':

        if (!pg_session_signed_in() || !defined('USER_ID') || ((int) USER_ID <= 0)) {
            passkey_answer(array('ok' => false, 'error' => 'session', 'message' => lang('You are not signed in. Sign in and try again.')), 401);
        }

        // As with the second factor, an administrator signed in as another
        // account cannot change how that account signs in.
        if (!empty($_SESSION['software']['logged_in_as_different_user'])) {
            passkey_answer(array('ok' => false, 'error' => 'denied', 'message' => lang('Passkeys cannot be changed while signed in as another user.')), 403);
        }

        $account = array(
            'id'       => (int) USER_ID,
            'username' => (string) USER_USERNAME,
            'email'    => defined('USER_EMAIL_ADDRESS') ? (string) USER_EMAIL_ADDRESS : '',
        );

        if ($action === 'register_options') {

            $result = pg_passkey_registration_begin($account);

            if (!$result['ok']) {
                passkey_refuse($result['error'], 'register');
            }

            passkey_answer(array('ok' => true, 'options' => $result['options']));

        }

        $result = pg_passkey_register($account, (string) ($_POST['credential'] ?? ''), (string) ($_POST['name'] ?? ''));

        if (!$result['ok']) {
            log_activity(lang(array('string' => 'passkey not added ({var:1})', 'vars' => $result['error'])), USER_USERNAME);
            passkey_refuse($result['error'], 'register');
        }

        log_activity(lang(array('string' => 'user added a passkey ({var:1})', 'vars' => $result['passkey']['name'])), USER_USERNAME);

        passkey_answer(array('ok' => true, 'passkey' => $result['passkey']));

        break;

    case 'signin_options':

        // The second step of a sign-in whose password was accepted
        // (pg_mfa_gate()); a sign-in with a passkey alone is not offered.
        $pending = pg_mfa_pending();

        if (($pending === null) || ($pending['mode'] !== 'verify') || pg_session_signed_in()) {
            passkey_answer(array('ok' => false, 'error' => 'session', 'message' => lang('No sign-in is waiting for its second step. Please sign in again.')), 403);
        }

        $result = pg_passkey_signin_begin((int) $pending['user_id']);

        if (!$result['ok']) {
            passkey_refuse($result['error'], 'signin');
        }

        passkey_answer(array('ok' => true, 'options' => $result['options']));

        break;
}

passkey_answer(array('ok' => false, 'error' => 'action', 'message' => lang('Unknown action.')), 400);
