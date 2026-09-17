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
 * and disconnect Google. State changes only; the my-account screen renders the
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

// Unknown or missing action.
go(get_page_type_url('my account'));
