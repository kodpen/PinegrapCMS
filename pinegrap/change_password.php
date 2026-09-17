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
 */

include('init.php');

$liveform = new liveform('change_password');

validate_token_field();

$liveform->add_fields_to_session();

// A signed-in Google-only account (algo 3) has no current password to type:
// this request sets the account's FIRST password. The bypass is tied strictly
// to the session identity - in this mode the posted email cannot pick the
// account, only the session user's own row is written.
$pg_set_password_mode = false;
if (isset($_SESSION['sessionuserid']) && $_SESSION['sessionuserid']) {
    $pg_set_password_mode = ((int) db_value("SELECT user_password_algo FROM user WHERE user_id = '" . (int) $_SESSION['sessionuserid'] . "'") === 3);
}

// validate required fields
$liveform->validate_required_field('email_address', lang('Email or username is required.'));
if (!$pg_set_password_mode) {
    $liveform->validate_required_field('current_password', lang('Current Password is required.'));
}
$liveform->validate_required_field('new_password', lang('New Password is required.'));
$liveform->validate_required_field('new_password_verify', lang('Please type new password again.'));

// Set-password mode: the account is the session's own; no password exists to
// verify. Otherwise validate the login as always.
if ($pg_set_password_mode) {
    $change_user_id = (int) $_SESSION['sessionuserid'];

// if there is not already an error for email_address and current password fields, validate login
} elseif (($liveform->check_field_error('email_address') == false) && ($liveform->check_field_error('current_password') == false)) {
    $pg_login_identifier = $liveform->get_field_value('email_address');

    // Verifying the current password is a sign-in in everything but name, and
    // this form is reachable without a session, so it is counted against the
    // same address and account limits as index.php. Without them this handler
    // is an unthrottled oracle for any account's password.
    check_banned_ip_addresses('login');
    pg_login_throttle_guard($pg_login_identifier);

    // The sign-in question screen cannot stand in for this form (it carries
    // no new-password fields), so once the failures reach the question
    // threshold the attempt is refused outright, before the password is
    // looked at. Signing in at the login form clears the account's counter.
    if (pg_login_captcha_required($pg_login_identifier)) {
        log_activity('access denied to change password (too many failed attempts) (email or username: ' . $pg_login_identifier . ')', 'UNKNOWN');
        $liveform->mark_error('current_password', lang('There have been several failed sign-in attempts for this account or from this address. Please sign in first, then change your password.'));
        $liveform->assign_field_value('current_password', '');
        go(get_page_type_url('change password'));
    }

    // if login is not valid, check which part of login is invalid. Raw password
    // now; validate_login() returns the user id, which we reuse below.
    $change_user_id = validate_login($pg_login_identifier, $liveform->get_field_value('current_password'));
    if ($change_user_id === false) {
        // A wrong password is counted before the visitor is told which half
        // was wrong, so the counter cannot be avoided by reading the message.
        pg_login_record_failure($pg_login_identifier);

        // if email_address exists, password is incorrect, so output error about password being incorrect
        if (validate_username($liveform->get_field_value('email_address')) == true) {
            log_activity('access denied to change password (password invalid) (email or username: ' . $liveform->get_field_value('email_address') . ')', 'UNKNOWN');
            $liveform->mark_error('current_password', lang('The current password you entered is incorrect. Please remember that passwords are case sensitive.'));
            $liveform->assign_field_value('current_password', '');
            
        // else email_address does not exist, so output error about email_address not existing
        } else {
            log_activity('access denied to change password (email or username invalid: ' . $liveform->get_field_value('email_address') . ')', 'UNKNOWN');
            $liveform->mark_error('email_address', lang('The email address or username you entered could not be found.'));
        }

    // The current password is right: forget this account's failures, as a
    // successful sign-in does.
    } else {
        pg_login_throttle_pass($pg_login_identifier);
    }
}

// if there is not already an error for the new password fields, check to see if new password and new password verify do not match
if (($liveform->check_field_error('new_password') == false) && ($liveform->check_field_error('new_password_verify') == false)) {
    if ($liveform->get_field_value('new_password') != $liveform->get_field_value('new_password_verify')) {
        $liveform->mark_error('new_password', lang('The two new passwords you entered did not match.'));
        $liveform->mark_error('new_password_verify');
        $liveform->assign_field_value('new_password', '');
        $liveform->assign_field_value('new_password_verify', '');
    }
}

// if there is not already an error for the new password field,
// and there is not already an error for the new password verify field,
// and strong password is enabled,
// and the new password is not strong,
// then mark error for new password fields
if (
    ($liveform->check_field_error('new_password') == false)
    && ($liveform->check_field_error('new_password_verify') == false)
    && (STRONG_PASSWORD == true)
    && (validate_password_strength($liveform->get_field_value('new_password')) == false)
) {
    $liveform->mark_error('new_password', lang('The new password you entered does not meet the requirements. Please enter a different new password.'));
    $liveform->mark_error('new_password_verify');
    $liveform->assign_field_value('new_password', '');
    $liveform->assign_field_value('new_password_verify', '');
}

// Check to see if the new password is in the password hint, and mark an error if so.
if (($liveform->get_field_value('password_hint') != '') && ($liveform->get_field_value('new_password') != '')) {
    if (
        ($liveform->get_field_value('password_hint') == $liveform->get_field_value('new_password'))
         || (mb_strpos(mb_strtolower($liveform->get_field_value('password_hint')), mb_strtolower($liveform->get_field_value('new_password'))) !== false) 
       ) {
            $liveform->mark_error('password_hint', lang('Your password hint cannot contain your password.'));
            $liveform->assign_field_value('password_hint', '');
    }
}

// if an error exists, then send user back to previous screen
if ($liveform->check_form_errors()) {
    go(get_page_type_url('change password'));
}

// Get the actual username for the user, because the user probably entered
// an email address for the username field.  We need the actual username
// because it is important that we store the actual username in the session and cookies.
// The current password was already verified above; read the canonical username
// by the id it returned.
$username = db_value(
    "SELECT user_username FROM user WHERE user_id = '" . (int) $change_user_id . "'");

// update password for user with a modern hash
$query = 
    "UPDATE user 
    SET 
        user_password = '" . escape(pg_password_hash($liveform->get_field_value('new_password'))) . "',
        user_password_algo = 2,
        user_password_hint = '" . escape($liveform->get_field_value('password_hint')) . "'
        " . pg_password_changed_sql() . "
    WHERE user_id = '" . (int) $change_user_id . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed');

// A password change invalidates every remembered session: drop them all, then
// mint one fresh token below for the browser doing the change so it stays in.
//
// If this browser's session was pinned, the replacement is pinned too. The pin
// is the operator's decision about which device this account may use, and a
// routine password change is not the member's way to overturn it.
$pinned_before = false;
if (isset($_COOKIE['software']['auth'])) {
    $pinned_parts = explode(':', (string) $_COOKIE['software']['auth'], 2);
    if ($pinned_parts[0] !== '') {
        $pinned_before = pg_auth_token_pinned($pinned_parts[0]);
    }
}

pg_auth_token_revoke_user($change_user_id);

// keep this browser signed in under the same user id
$_SESSION['sessionuserid']  = $change_user_id;
$_SESSION['sessionusername'] = $username;
log_activity("user changed password", $username);

// The revoke above took THIS browser's token with it, so a fresh one has to
// replace it or the session is unbound and the next request signs it out.
// pg_login_set_device_cookie() mints the right kind: a remembered 30-day
// token when the browser was remembered, otherwise a session token (which it
// creates on every sign-in now, so the session stays listed and revocable).
if (($_SESSION['software']['logged_in_as_different_user'] ?? false) == false) {
    $keep_remembered = ((REMEMBER_ME == true) && (isset($_COOKIE['software']['auth']) == true));
    $new_selector = pg_login_set_device_cookie($change_user_id, $keep_remembered);

    if ($pinned_before && $new_selector && pg_auth_tokens_have_pin()) {
        db("UPDATE auth_tokens SET pinned = 1 WHERE selector = '" . escape($new_selector) . "'");
    }
}

$my_account = new liveform('my_account');

$my_account->add_notice(lang('Your password has been changed.'));

$liveform->remove();

go(get_page_type_url('my account'));