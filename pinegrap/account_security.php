<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 *
 * Account security actions for the signed-in member: sign out of all devices,
 * disconnect Google, and turn two-step verification on or off. State changes only; the my-account screen renders the
 * matching buttons (pg_account_security_section). Every action requires a POST
 * with a valid CSRF token, and the Google-only guard is enforced here so the
 * screen cannot be bypassed.
 */

include('init.php');

// Must be signed in.
if (pg_session_signed_in() == false) {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php');
    exit();
}

// Only a POST carrying a valid CSRF token may change anything; anything else
// just returns to the account screen.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    go(get_page_type_url('my account'));
}
validate_token_field();

$user_id = (int) ($_SESSION['sessionuserid'] ?? 0);
if ($user_id <= 0) {
    go(PATH);
}

$action = isset($_POST['pg_security_action']) ? (string) $_POST['pg_security_action'] : '';

// Sign out of all devices: revoke every remember-me token for this account,
// clear this browser's cookie, end this session, and send the visitor back to
// sign in. The clearest "sign out everywhere" - the current device included.
if ($action === 'logout_all') {
    // Pinned sessions are the operator's decision and survive this; everything
    // else of this account's goes, including this browser unless it is the
    // pinned one.
    pg_auth_token_revoke_user($user_id, true);
    // Only end THIS browser's session when its own token actually went; a
    // pinned browser stays signed in, which is what pinning means.
    $current_selector = '';
    if (isset($_COOKIE['software']['auth'])) {
        $auth_parts = explode(':', (string) $_COOKIE['software']['auth'], 2);
        $current_selector = (string) $auth_parts[0];
    }

    log_activity(lang('user signed out of all devices'), $_SESSION['sessionusername'] ?? '');

    if (($current_selector !== '') && pg_auth_token_pinned($current_selector)) {
        go(get_page_type_url('my account'));
    }

    setcookie('software[auth]', '', time() - 1000, '/');
    unset($_COOKIE['software']['auth']);
    session_unset();
    session_destroy();
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php');
    exit();
}

// Disconnect Google: allowed only while the account still has a password to
// sign in with. A Google-only account (algo 3) would lock itself out, so it is
// refused here regardless of what the screen offered.
if ($action === 'unlink_google') {
    $algo = (int) db_value("SELECT user_password_algo FROM user WHERE user_id = '" . $user_id . "'");
    if ($algo === 3) {
        output_error(lang('This account signs in with Google only. Set a password first with Forgot Password, then you can disconnect Google.'));
    }
    db("UPDATE user SET user_google_id = NULL WHERE user_id = '" . $user_id . "'");
    log_activity(lang('user disconnected Google'), $_SESSION['sessionusername'] ?? '');
    go(get_page_type_url('my account'));
}

// Sign out one device: revoke a single token belonging to this account. If it
// is the current browser's own token, end this session too - identical to
// signing out here.
if ($action === 'revoke_device') {
    $selector = isset($_POST['pg_selector']) ? (string) $_POST['pg_selector'] : '';
    if (preg_match('/^[a-f0-9]{24}$/', $selector)) {
        $owner_id = (int) db_value("SELECT user_id FROM auth_tokens WHERE selector = '" . escape($selector) . "'");

        // Only this account's own token; a stale or foreign selector is ignored.
        if ($owner_id === $user_id) {
            // A pinned session cannot be signed out from here - if a member
            // could release it themselves, pinning would mean nothing.
            if (pg_auth_token_pinned($selector)) {
                output_error(lang('This device was locked by the site owner and cannot be signed out here.'));
            }

            pg_auth_token_revoke($selector);
            log_activity(lang('user signed out one device'), $_SESSION['sessionusername'] ?? '');

            $current_selector = '';
            if (isset($_COOKIE['software']['auth'])) {
                $auth_parts = explode(':', (string) $_COOKIE['software']['auth'], 2);
                $current_selector = (string) $auth_parts[0];
            }
            if (($current_selector !== '') && hash_equals($selector, $current_selector)) {
                setcookie('software[auth]', '', time() - 1000, '/');
                unset($_COOKIE['software']['auth']);
                session_unset();
                session_destroy();
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php');
                exit();
            }
        }
    }
    go(get_page_type_url('my account'));
}

// Two-step verification (includes/fn/mfa.php). Each action returns to the
// profile page, which is where pg_account_security_section() prints the
// controls; a refused code is reported through that page's form messages.
if (strpos($action, 'mfa_') === 0) {

    $mfa_back = pg_mfa_account_url();
    $mfa_messages = new liveform('my_account_profile');
    $mfa_username = (string) ($_SESSION['sessionusername'] ?? '');
    $mfa_code = (isset($_POST['code']) && is_scalar($_POST['code'])) ? (string) $_POST['code'] : '';

    // An operator signed in as this person cannot change the person's
    // second factor; the screen offers nothing to press in that mode either.
    if (!empty($_SESSION['software']['logged_in_as_different_user'])) {
        output_error(lang('Two-step verification cannot be changed while signed in as another user.'));
    }

    // Before the 8.40 upgrade there is nothing to act on.
    if (!pg_mfa_table_exists()) {
        go($mfa_back);
    }

    // Setting up a key, or checking a TOTP code, needs the key readable.
    // Turning it off (with a recovery code) and dropping an unfinished setup
    // do not, so a site that lost ENCRYPTION_KEY can still get out of it.
    if (in_array($action, array('mfa_begin', 'mfa_confirm', 'mfa_recovery_regenerate'), true) && !pg_mfa_available()) {
        output_error(lang('Two-step verification cannot be turned on because the site has no encryption key. Please ask the site owner.'));
    }

    // Revoking the account's tokens (turning it on or off does) took this
    // browser's too; it gets a fresh one so it stays signed in, as after a
    // password change.
    $mfa_keep_this_browser = function () use ($user_id) {
        $keep_remembered = ((REMEMBER_ME == true) && isset($_COOKIE['software']['auth']));
        pg_login_set_device_cookie($user_id, $keep_remembered);
    };

    if ($action === 'mfa_begin') {
        if (!pg_mfa_enabled($user_id)) {
            pg_mfa_begin_setup($user_id);
        }
        go($mfa_back);
    }

    if ($action === 'mfa_cancel_setup') {
        db("UPDATE user_mfa SET pending_secret = '', pending_at = 0
            WHERE user_id = '" . $user_id . "' AND enabled_at = 0");
        db("DELETE FROM user_mfa WHERE user_id = '" . $user_id . "' AND enabled_at = 0 AND totp_secret = ''");
        go($mfa_back);
    }

    if ($action === 'mfa_codes_seen') {
        unset($_SESSION['software']['mfa_codes_show']);
        go($mfa_back);
    }

    // Everything below checks a code, and every check is an attempt.
    if (in_array($action, array('mfa_confirm', 'mfa_recovery_regenerate', 'mfa_disable'), true)
        && pg_mfa_attempt_blocked($user_id)) {
        log_activity(lang('access denied (too many two-step verification attempts)'), $mfa_username);
        output_error(lang('Too many attempts. Please wait a few minutes and try again.'));
    }

    if ($action === 'mfa_confirm') {
        $codes = pg_mfa_confirm_setup($user_id, $mfa_code);

        if ($codes === false) {
            $mfa_messages->add_error(lang('That code was not accepted. Check the time on your phone and try again.'));
            go($mfa_back);
        }

        pg_mfa_attempt_clear($user_id);
        $_SESSION['software']['mfa_codes_show'] = $codes;
        $mfa_keep_this_browser();
        go($mfa_back);
    }

    if ($action === 'mfa_recovery_regenerate') {
        if (pg_mfa_verify_code($user_id, $mfa_code) !== 'totp') {
            $mfa_messages->add_error(lang('That code was not accepted. Check the time on your phone and try again.'));
            go($mfa_back);
        }

        pg_mfa_attempt_clear($user_id);
        $_SESSION['software']['mfa_codes_show'] = pg_mfa_recovery_regenerate($user_id);
        log_activity(lang('user created new two-step recovery codes'), $mfa_username);
        go($mfa_back);
    }

    if ($action === 'mfa_disable') {
        // A Google-only account (algo 3) has no password to ask for; the
        // code is the proof there.
        $algo = (int) db_value("SELECT user_password_algo FROM user WHERE user_id = '" . $user_id . "'");

        if ($algo !== 3) {
            // This form accepts a password, so it is throttled like the
            // sign-in forms: a stolen session must not become a free
            // password oracle.
            pg_login_throttle_guard($mfa_username);

            $current_password = (isset($_POST['current_password']) && is_scalar($_POST['current_password'])) ? (string) $_POST['current_password'] : '';

            if (($current_password === '') || ((int) validate_login($mfa_username, $current_password) !== $user_id)) {
                pg_login_record_failure($mfa_username);
                $mfa_messages->add_error(lang('The password you entered is incorrect. Please remember that passwords are case sensitive.'));
                go($mfa_back);
            }
        }

        if (pg_mfa_verify_code($user_id, $mfa_code) === false) {
            $mfa_messages->add_error(lang('That code was not accepted. Check the time on your phone and try again.'));
            go($mfa_back);
        }

        pg_mfa_attempt_clear($user_id);
        pg_mfa_disable($user_id);
        unset($_SESSION['software']['mfa_codes_show']);
        $mfa_keep_this_browser();
        log_activity(lang('user turned off two-step verification'), $mfa_username);
        go($mfa_back);
    }

    go($mfa_back);
}

// Unknown or missing action.
go(get_page_type_url('my account'));
