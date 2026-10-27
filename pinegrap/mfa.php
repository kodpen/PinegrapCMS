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
 * authenticator app, a recovery code or a passkey (a passkey or a recovery
 * code, for an account with passkeys and no authenticator key); 'setup' has
 * the account add a key to its app, confirm it with the first code and save
 * its recovery codes. Only then is the sign-in completed, in the same order the sign-in
 * flows use.
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

// The tables went away after the gate wrote the record, or setup was owed
// while the key could still be encrypted and no longer can: neither can be
// completed here. Verification goes on without the encryption key - the
// stored key cannot be read then, but the recovery codes are only hashed -
// so a missing ENCRYPTION_KEY never turns into a sign-in by password alone.
if (!pg_mfa_table_exists() || (!pg_mfa_available() && ($pending['mode'] === 'setup'))) {
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
                $error = lang('That code was not accepted. Each code works once: wait for the next one, and check the time on your phone.');
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

    } elseif (($mode === 'verify') && ($action === 'passkey')) {

        // A passkey of the account answers the second step in place of a
        // code (passkey.php gave the options); it counts as an attempt the
        // same way.
        if (pg_mfa_attempt_blocked($user_id)) {
            $error = pg_mfa_screen_blocked($username);
        } else {
            $credential = (isset($_POST['credential']) && is_string($_POST['credential'])) ? $_POST['credential'] : '';
            $result = pg_passkey_signin_check($credential, $user_id);

            if (!$result['ok']) {
                log_activity(lang(array(
                    'string' => 'access denied (passkey not accepted: {var:1})',
                    'vars'   => array($result['error']))), $username);
                $error = pg_passkey_refusal($result['error'], 'signin');
            } else {
                pg_mfa_attempt_clear($user_id);

                log_activity(lang(array(
                    'string' => 'user signed in with a passkey ({var:1})',
                    'vars'   => array($result['name']))), $username);

                pg_mfa_screen_complete($user_id, $username, $send_to, $remember);
            }
        }

    } elseif (($mode === 'setup') && ($action === 'setup_confirm') && empty($pending['codes'])) {

        if (pg_mfa_attempt_blocked($user_id)) {
            $error = pg_mfa_screen_blocked($username);
        } else {
            $codes = pg_mfa_confirm_setup($user_id, $code);

            if ($codes === false) {
                $error = lang('That code was not accepted. Each code works once: wait for the next one, and check the time on your phone.');
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
    ? '<div class="alert theme-danger" role="alert"><i class="bi bi-exclamation-triangle-fill me-3" aria-hidden="true"></i>' . h($error) . '</div>'
    : '';

$cancel_button = '<button type="submit" name="pg_mfa_action" value="cancel" class="btn btn-outline theme-secondary w-100 mt-3" formnovalidate="formnovalidate">' . h(lang('Cancel')) . '</button>';

// The code field: Bootstrap's OTP component, one real input drawn as slots
// (data-bs-otp starts it). It never submits by itself: a refused code costs
// one of the attempts pg_mfa_attempt_blocked() allows, so the person sends
// it. The recovery field takes XXXX-XXXX (case, dash and spaces do not
// matter, pg_mfa_recovery_normalize()); the field not in use is disabled, so
// only one "code" is posted.
function pg_mfa_screen_code_field($kind, $active)
{
    $recovery = ($kind === 'recovery');

    return '<div class="mb-3" id="mfa_' . $kind . '_box"' . ($active ? '' : ' hidden="hidden"') . '>
        <label for="mfa_' . $kind . '" class="form-label">' . h($recovery ? lang('Recovery code') : lang('Code')) . '</label>
        <div class="otp pg-otp-fluid' . ($recovery ? ' text-uppercase' : ' otp-lg') . '" data-bs-otp=""'
            . ($recovery ? ' data-bs-type="alphanumeric" data-bs-groups="[4,4]" data-bs-separator="-"' : ' data-bs-groups="[3,3]"') . '>
            <input type="text" id="mfa_' . $kind . '" name="code" class="otp-input" maxlength="' . ($recovery ? 8 : 6) . '" required="required"'
            . ($recovery ? ' autocomplete="off" autocapitalize="characters"' : ' inputmode="numeric" autocomplete="one-time-code"')
            . ' spellcheck="false"' . ($active ? ' autofocus="autofocus"' : ' disabled="disabled"') . '/>
        </div>
    </div>';
}

// Narrow screens: the slots shrink with the card instead of running past it.
$otp_style = '<style>
    @layer custom {
        .pg-otp-fluid .otp-slots, .pg-otp-fluid .otp-slot { min-width: 0; }
    }
    </style>';

if (($mode === 'setup') && !empty($pending['codes'])) {

    $title = lang('Save your recovery codes');
    $codes_text = implode("\n", $pending['codes']);

    $body =
        '<p class="fg-secondary mb-5">' . h(lang('Each code signs you in once if you lose your phone. Keep them somewhere safe; they are not shown again.')) . '</p>
        <pre class="border rounded-4 p-5 mb-5 text-center fs-lg" style="letter-spacing: .08em;">' . h($codes_text) . '</pre>
        <p class="mb-7"><a class="btn btn-sm btn-outline theme-secondary" download="recovery-codes.txt" href="data:text/plain;charset=utf-8,' . h(rawurlencode($codes_text . "\n")) . '"><i class="bi bi-download me-1" aria-hidden="true"></i>' . h(lang('Download')) . '</a></p>
        <form name="mfa_codes" action="' . $form_action . '" method="post">
            ' . $hidden . '
            <button type="submit" name="pg_mfa_action" value="setup_done" class="btn btn-solid theme-primary w-100">' . h(lang('I have saved them, continue')) . '</button>
        </form>';

} elseif ($mode === 'setup') {

    $title = lang('Set up two-step verification');
    $secret = pg_mfa_begin_setup($user_id);
    $account = (string) db_value("SELECT user_email FROM user WHERE user_id = '" . $user_id . "'");
    $key_uri = pg_totp_uri(pg_mfa_issuer(), ($account !== '') ? $account : $username, $secret);

    // Drawn here as inline SVG, so the key is never part of an image URL.
    // Should no code come out, the key is still there to type in.
    $qr = pg_qr_svg($key_uri, 208, array('label' => lang('QR code for the authenticator app'), 'class' => 'd-block'));

    $key_intro = ($qr !== '')
        ? '<p class="mb-3">' . h(lang('Scan the QR code with your authenticator app (Google Authenticator, Aegis, 1Password, Microsoft Authenticator), then enter the code it shows.')) . '</p>
        <div class="text-center mb-5"><div class="d-inline-block bg-white border rounded-4 p-3">' . $qr . '</div></div>
        <p class="mb-3">' . h(lang('If you cannot scan it, add the key by hand:')) . '</p>'
        : '<p class="mb-3">' . h(lang('Add the key to your authenticator app by hand (Google Authenticator, Aegis, 1Password, Microsoft Authenticator), then enter the code it shows.')) . '</p>';

    $body =
        '<p class="fg-secondary mb-5">' . h(lang('Your account must use two-step verification: after your password, a code from an authenticator app on your phone.')) . '</p>
        ' . $key_intro . '
        <p class="mb-3"><code id="mfa_key" class="fs-lg user-select-all">' . h(pg_mfa_format_secret($secret)) . '</code></p>
        <div class="input-group input-group-sm mb-7">
            <input type="text" id="mfa_uri" class="form-control" readonly="readonly" value="' . h($key_uri) . '" aria-label="' . h(lang('Key link')) . '"/>
            <button type="button" class="btn btn-outline theme-secondary" id="mfa_copy"><i class="bi bi-clipboard me-1" aria-hidden="true"></i>' . h(lang('Copy')) . '</button>
        </div>
        ' . $messages . '
        <form name="mfa_setup" action="' . $form_action . '" method="post" autocomplete="off">
            ' . $hidden . '
            ' . pg_mfa_screen_code_field('totp', true) . '
            <button type="submit" name="pg_mfa_action" value="setup_confirm" class="btn btn-solid theme-primary w-100">' . h(lang('Continue')) . '</button>
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

    // An account with passkeys and no authenticator key is asked for a
    // passkey, or one of the recovery codes it got with its first passkey;
    // there is no authenticator code to type.
    $totp_on = pg_mfa_enabled($user_id);
    $recovery_left = $totp_on ? 0 : pg_mfa_recovery_remaining($user_id);

    // A passkey of the account, offered above the code when the browser can
    // use one (the script shows the block). The browser's answer is posted
    // back here and checked like a code.
    $passkey_block = '';

    if (pg_mfa_has_passkey($user_id)) {
        $passkey_block =
            ($totp_on ? '' : '<div id="mfa_passkey_unsupported" class="alert theme-warning" role="alert"><i class="bi bi-exclamation-triangle-fill me-3" aria-hidden="true"></i>' . h(lang('Your browser could not use a passkey here.')) . '</div>') . '
            <div id="mfa_passkey" class="d-none">
                <form name="mfa_passkey" action="' . $form_action . '" method="post">
                    ' . $hidden . '
                    <input type="hidden" name="pg_mfa_action" value="passkey"/>
                    <input type="hidden" name="credential" value=""/>
                    <button type="button" id="mfa_passkey_button" class="btn btn-solid theme-primary w-100"><i class="bi bi-key me-1" aria-hidden="true"></i>' . h(lang('Use a passkey')) . '</button>
                </form>
                <div id="mfa_passkey_error" class="alert theme-danger mt-5 mb-0 d-none" role="alert"></div>
                ' . (($totp_on || ($recovery_left > 0)) ? '<p class="text-center fg-secondary small my-5">' . h(lang('or enter a code')) . '</p>' : '') . '
            </div>
            <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_passkey.js?v=' . (int) @filemtime(dirname(__FILE__) . '/assets/js/pg_passkey.js') . '"></script>
            <script>
            (function () {
                var block = document.getElementById("mfa_passkey");
                if (!block || !window.PgPasskey || !window.PgPasskey.supported()) {
                    return;
                }
                var form = document.forms.mfa_passkey;
                var button = document.getElementById("mfa_passkey_button");
                var notice = document.getElementById("mfa_passkey_error");
                var texts = ' . json_encode(array(
                    'cancelled' => lang('The passkey was not used. Please try again.'),
                    'failed'    => lang('Your browser could not use a passkey here.'),
                ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
                block.classList.remove("d-none");
                var unsupported = document.getElementById("mfa_passkey_unsupported");
                if (unsupported) {
                    unsupported.classList.add("d-none");
                }
                button.addEventListener("click", function () {
                    button.disabled = true;
                    notice.classList.add("d-none");
                    window.PgPasskey.signIn({
                        url: ' . json_encode(PATH . SOFTWARE_DIRECTORY . '/passkey.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . ',
                        token: form.elements.token.value
                    }).then(function (credential) {
                        form.elements.credential.value = JSON.stringify(credential);
                        form.submit();
                    }).catch(function (error) {
                        button.disabled = false;
                        notice.textContent = error.message || ((error.code === "NotAllowedError" || error.code === "AbortError") ? texts.cancelled : texts.failed);
                        notice.classList.remove("d-none");
                    });
                });
            })();
            </script>';
    }

    // Recovery mode first when the authenticator keys cannot be read, or
    // when the refused code was a recovery code.
    $recovery_mode = !pg_mfa_available() || (($error !== '') && (pg_mfa_code_kind($code ?? '') === 'recovery'));

    if (!$totp_on && ($recovery_left > 0)) {
        $body =
            '<p class="fg-secondary mb-5">' . h(lang('Use one of your passkeys, or one of your recovery codes, to finish signing in.')) . '</p>
            ' . $messages . '
            ' . $passkey_block . '
            <form name="mfa_verify" action="' . $form_action . '" method="post" autocomplete="off">
                ' . $hidden . '
                ' . pg_mfa_screen_code_field('recovery', true) . '
                <button type="submit" name="pg_mfa_action" value="verify" class="btn btn-outline theme-secondary w-100 mt-3">' . h(lang('Verify')) . '</button>
                ' . $cancel_button . '
            </form>';
    } elseif (!$totp_on) {
        $body =
            '<p class="fg-secondary mb-5">' . h(lang('Use one of your passkeys to finish signing in.')) . '</p>
            ' . $messages . '
            ' . $passkey_block . '
            <form name="mfa_cancel" action="' . $form_action . '" method="post">
                ' . $hidden . '
                ' . $cancel_button . '
            </form>';
    } else {
        $body =
            '<p class="fg-secondary mb-5">' . h(lang('Enter the 6-digit code from your authenticator app, or one of your recovery codes.')) . '</p>
            ' . (pg_mfa_available() ? '' : '<div class="alert theme-warning" role="alert"><i class="bi bi-exclamation-triangle-fill me-3" aria-hidden="true"></i>' . h(lang('The site cannot read authenticator keys right now; use one of your recovery codes, or ask the site owner.')) . '</div>') . '
            ' . $messages . '
            ' . $passkey_block . '
            <form name="mfa_verify" action="' . $form_action . '" method="post" autocomplete="off">
                ' . $hidden . '
                ' . pg_mfa_screen_code_field('totp', !$recovery_mode) . '
                ' . pg_mfa_screen_code_field('recovery', $recovery_mode) . '
                <p class="mb-5">
                    <button type="button" class="btn btn-sm btn-text theme-primary px-0" id="mfa_use_recovery"' . ($recovery_mode ? ' hidden="hidden"' : '') . '>' . h(lang('Use a recovery code')) . '</button>
                    <button type="button" class="btn btn-sm btn-text theme-primary px-0" id="mfa_use_totp"' . ($recovery_mode ? '' : ' hidden="hidden"') . '>' . h(lang('Use a code from the app')) . '</button>
                </p>
                <button type="submit" name="pg_mfa_action" value="verify" class="btn btn-solid theme-primary w-100">' . h(lang('Verify')) . '</button>
                ' . $cancel_button . '
            </form>
            <script>
            (function () {
                // Swaps the app code and the recovery code field: the hidden
                // one is disabled, so it is neither posted nor required.
                function use(kind) {
                    var other = (kind === "recovery") ? "totp" : "recovery";
                    var input = document.getElementById("mfa_" + kind);
                    document.getElementById("mfa_" + kind + "_box").hidden = false;
                    document.getElementById("mfa_" + other + "_box").hidden = true;
                    document.getElementById("mfa_" + other).disabled = true;
                    input.disabled = false;
                    document.getElementById("mfa_use_" + other).hidden = false;
                    document.getElementById("mfa_use_" + kind).hidden = true;
                    input.focus();
                }
                document.getElementById("mfa_use_recovery").addEventListener("click", function () { use("recovery"); });
                document.getElementById("mfa_use_totp").addEventListener("click", function () { use("totp"); });
            })();
            </script>';
    }
}

echo output_header_secure(array('title' => $title))
    . '<main class="container py-7 md:py-12">
        <div class="card mx-auto shadow-sm" style="max-width: 28rem;">
            <div class="card-body p-7">
                <h1 class="h4 mb-5"><i class="bi bi-shield-lock me-3" aria-hidden="true"></i>' . h($title) . '</h1>
                ' . $otp_style . '
                ' . $body . '
            </div>
        </div>
    </main>'
    . output_footer_secure();
