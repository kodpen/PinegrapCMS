<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Second step of a sign-in.
 *
 * A sign-in flow that accepted the password (or Google) of an account with a
 * second factor - or of an account whose role must have one - stashes a
 * short-lived pending record (pg_mfa_gate()) and sends the visitor here
 * before anything is signed in. 'verify' asks for a code from the
 * authenticator app or a recovery code; 'setup' has the account add a key to
 * its app, confirm it with the first code and save its recovery codes. Only
 * then is the sign-in completed, in the same order the sign-in flows use.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

$pending = pg_mfa_pending();

// Nothing pending (never started, expired, cancelled or completed): back to
// the sign-in screen.
if ($pending === null) {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/');
    exit();
}

if (pg_session_signed_in()) {
    pg_mfa_pending_clear();
    send_user_to_login_home();
}

// The tables went away or the encryption key was removed after the gate
// wrote the record. The gate only writes 'setup' while the feature is
// available, so neither mode can be completed without a second step here.
if (!pg_mfa_available()) {
    pg_mfa_pending_clear();
    output_error(lang('Two-step verification is not available on this site right now. Please ask the site owner for help.'));
}

$user_id  = (int) $pending['user_id'];
$username = (string) $pending['username'];
$send_to  = (string) $pending['send_to'];
$remember = !empty($pending['remember']);
$mode     = (string) $pending['mode'];

// Where Cancel goes: the screen the sign-in started on, from a fixed list.
$origin = in_array($pending['origin'] ?? '', array('index.php', 'membership_entrance.php', 'registration_entrance.php'), true)
    ? (string) $pending['origin'] : 'index.php';

$back_url = URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . (($origin === 'index.php') ? '' : $origin)
    . (($send_to !== '') ? ('?send_to=' . urlencode($send_to)) : '');

// Complete the sign-in the way the sign-in flows do once nothing more is
// owed: device limit (told the second step is done), device token, session,
// order. send_user_to_login_home() reads send_to from the request; every form
// on this screen carries it as a hidden field.
function pg_mfa_screen_complete($user_id, $username, $send_to, $remember)
{
    pg_mfa_pending_clear();

    pg_device_limit_gate($user_id, $username, $send_to, $remember, true);
    pg_login_set_device_cookie($user_id, $remember);

    if (REMEMBER_ME == TRUE) {
        setcookie('software[remember_me]', $remember ? 'true' : 'false', time() + 315360000, '/');
    }

    pg_session_sign_in($user_id, $username);

    require_once(dirname(__FILE__) . '/connect_user_to_order.php');
    connect_user_to_order();

    log_activity(lang('user signed in with two-step verification'), $username);

    send_user_to_login_home();
    exit();
}

// An attempt over the allowance is recorded in the firewall log as well as
// refused, so a guessing run shows up where an operator watches for probing.
function pg_mfa_screen_blocked($username)
{
    log_activity(lang('access denied (too many two-step verification attempts)'), $username);

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event(
            (!function_exists('waf_mode') || waf_mode() === 'block') ? 'rate' : 'would-rate',
            'rate-mfa',
            'rate',
            7,
            'mfa.php',
            '10min');
    }

    if (!headers_sent()) {
        http_response_code(429);
        header('Retry-After: 600');
    }

    return lang('Too many attempts. Please wait a few minutes and try again.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $action = isset($_POST['pg_mfa_action']) && is_scalar($_POST['pg_mfa_action']) ? (string) $_POST['pg_mfa_action'] : '';
    $code   = isset($_POST['code']) && is_scalar($_POST['code']) ? (string) $_POST['code'] : '';

    if ($action === 'cancel') {
        pg_mfa_pending_clear();
        header('Location: ' . $back_url);
        exit();
    }

    if (($mode === 'verify') && ($action === 'verify')) {

        if (pg_mfa_attempt_blocked($user_id)) {
            $error = pg_mfa_screen_blocked($username);
        } else {
            $result = pg_mfa_verify_code($user_id, $code);

            if ($result === false) {
                log_activity(lang('access denied (two-step code invalid)'), $username);
                $error = lang('That code was not accepted. Check the time on your phone and try again.');
            } else {
                pg_mfa_attempt_clear($user_id);

                if ($result === 'recovery') {
                    log_activity(lang(array(
                        'string' => 'user signed in with a recovery code ({var:1} left)',
                        'vars'   => array((string) pg_mfa_recovery_remaining($user_id)))), $username);
                }

                pg_mfa_screen_complete($user_id, $username, $send_to, $remember);
            }
        }

    } elseif (($mode === 'setup') && ($action === 'setup_confirm') && empty($pending['codes'])) {

        if (pg_mfa_attempt_blocked($user_id)) {
            $error = pg_mfa_screen_blocked($username);
        } else {
            $codes = pg_mfa_confirm_setup($user_id, $code);

            if ($codes === false) {
                $error = lang('That code was not accepted. Check the time on your phone and try again.');
            } else {
                pg_mfa_attempt_clear($user_id);

                // The codes are shown once, from the session, so a reload of
                // the next screen still has them; the record is renewed so the
                // time spent copying them does not run it out.
                $_SESSION['software']['mfa_pending']['codes'] = $codes;
                $_SESSION['software']['mfa_pending']['time'] = time();

                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/mfa.php');
                exit();
            }
        }

    } elseif (($mode === 'setup') && ($action === 'setup_done') && !empty($pending['codes'])) {

        pg_mfa_screen_complete($user_id, $username, $send_to, $remember);
    }
}

// -- screens -----------------------------------------------------------------

$form_action = h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/mfa.php');

$hidden = get_token_field() . '<input type="hidden" name="send_to" value="' . h($send_to) . '"/>';

$messages = ($error !== '')
    ? '<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>' . h($error) . '</div>'
    : '';

$cancel_button = '<button type="submit" name="pg_mfa_action" value="cancel" class="btn btn-outline-secondary w-100 mt-2" formnovalidate="formnovalidate">' . h(lang('Cancel')) . '</button>';

$code_input =
    '<div class="mb-3">
        <label for="mfa_code" class="form-label">' . h(lang('Code')) . '</label>
        <input type="text" id="mfa_code" name="code" class="form-control form-control-lg" required="required" inputmode="numeric" autocomplete="one-time-code" autofocus="autofocus" spellcheck="false" maxlength="20"/>
    </div>';

if (($mode === 'setup') && !empty($pending['codes'])) {

    $title = lang('Save your recovery codes');
    $codes_text = implode("\n", $pending['codes']);

    $body =
        '<p class="text-muted mb-3">' . h(lang('Each code signs you in once if you lose your phone. Keep them somewhere safe; they are not shown again.')) . '</p>
        <pre class="border rounded p-3 mb-3 text-center fs-5" style="letter-spacing: .08em;">' . h($codes_text) . '</pre>
        <p class="mb-4"><a class="btn btn-sm btn-outline-secondary" download="recovery-codes.txt" href="data:text/plain;charset=utf-8,' . h(rawurlencode($codes_text . "\n")) . '"><i class="bi bi-download me-1" aria-hidden="true"></i>' . h(lang('Download')) . '</a></p>
        <form name="mfa_codes" action="' . $form_action . '" method="post">
            ' . $hidden . '
            <button type="submit" name="pg_mfa_action" value="setup_done" class="btn btn-primary w-100">' . h(lang('I have saved them, continue')) . '</button>
        </form>';

} elseif ($mode === 'setup') {

    $title = lang('Set up two-step verification');
    $secret = pg_mfa_begin_setup($user_id);
    $account = (string) db_value("SELECT user_email FROM user WHERE user_id = '" . $user_id . "'");

    $body =
        '<p class="text-muted mb-3">' . h(lang('Your account must use two-step verification: after your password, a code from an authenticator app on your phone.')) . '</p>
        <p class="mb-2">' . h(lang('Add the key to your authenticator app by hand (Google Authenticator, Aegis, 1Password, Microsoft Authenticator), then enter the code it shows.')) . '</p>
        <p class="mb-2"><code id="mfa_key" class="fs-5 user-select-all">' . h(pg_mfa_format_secret($secret)) . '</code></p>
        <div class="input-group input-group-sm mb-4">
            <input type="text" id="mfa_uri" class="form-control" readonly="readonly" value="' . h(pg_totp_uri(pg_mfa_issuer(), ($account !== '') ? $account : $username, $secret)) . '" aria-label="' . h(lang('Key link')) . '"/>
            <button type="button" class="btn btn-outline-secondary" id="mfa_copy"><i class="bi bi-clipboard me-1" aria-hidden="true"></i>' . h(lang('Copy')) . '</button>
        </div>
        ' . $messages . '
        <form name="mfa_setup" action="' . $form_action . '" method="post" autocomplete="off">
            ' . $hidden . '
            ' . $code_input . '
            <button type="submit" name="pg_mfa_action" value="setup_confirm" class="btn btn-primary w-100">' . h(lang('Continue')) . '</button>
            ' . $cancel_button . '
        </form>
        <script>
        document.getElementById("mfa_copy").addEventListener("click", function () {
            var field = document.getElementById("mfa_uri");
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(field.value).catch(function () { field.select(); });
            } else {
                field.select();
            }
        });
        </script>';

} else {

    $title = lang('Two-Step Verification');

    $body =
        '<p class="text-muted mb-3">' . h(lang('Enter the 6-digit code from your authenticator app, or one of your recovery codes.')) . '</p>
        ' . $messages . '
        <form name="mfa_verify" action="' . $form_action . '" method="post" autocomplete="off">
            ' . $hidden . '
            ' . $code_input . '
            <button type="submit" name="pg_mfa_action" value="verify" class="btn btn-primary w-100">' . h(lang('Verify')) . '</button>
            ' . $cancel_button . '
        </form>';
}

echo output_header_secure(array('title' => $title))
    . '<main class="container py-4 py-md-5">
        <div class="card mx-auto shadow-sm" style="max-width: 28rem;">
            <div class="card-body p-4">
                <h1 class="h4 mb-3"><i class="bi bi-shield-lock me-2" aria-hidden="true"></i>' . h($title) . '</h1>
                ' . $body . '
            </div>
        </div>
    </main>'
    . output_footer_secure();
