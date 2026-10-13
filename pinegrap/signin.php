<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sign in with Microsoft, Apple and the other providers of
 * includes/fn/signin.php. Google has its own screen, google_auth.php.
 *
 * One file, two phases:
 *   ?provider=  START:    plant state, nonce and PKCE in the session, send the
 *                         visitor to the provider.
 *   state=      CALLBACK: trade the code for an id_token, find the account by
 *                         the identity, else tie one with the same verified
 *                         e-mail, else open one where the screen allows it,
 *                         then sign in. Microsoft answers with a GET, Apple
 *                         posts its answer (form_post).
 *
 * The return address to register with each provider is exactly this file's
 * address with no query (pg_signin_redirect_uri(); the security settings
 * show it).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$params = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') ? $_POST : $_GET;

// ---------------------------------------------------------------------------
// START
// ---------------------------------------------------------------------------
if (!isset($params['state']) && !isset($params['code']) && !isset($params['error'])) {

    $provider_key = (isset($_GET['provider']) && is_string($_GET['provider'])) ? $_GET['provider'] : '';

    if (!pg_signin_enabled($provider_key)) {
        go(PATH);
    }

    $start = pg_signin_start(
        $provider_key,
        (isset($_GET['send_to']) && is_string($_GET['send_to'])) ? $_GET['send_to'] : '',
        (isset($_GET['create']) && is_string($_GET['create'])) ? $_GET['create'] : 'create');

    if (!$start['ok']) {
        output_error($start['error']);
    }

    // A provider that posts its answer back (Apple's form_post) is a
    // cross-site POST, which a browser sends without a Lax session cookie;
    // the session the state is in would be lost. Same remedy as the payment
    // gateway returns.
    $providers = pg_signin_providers();

    if (isset($providers[$provider_key]['authorize_params']['response_mode'])
        && ($providers[$provider_key]['authorize_params']['response_mode'] === 'form_post')) {
        pg_session_cookie_allow_cross_site();
    }

    header('Location: ' . $start['url']);
    exit();
}

// ---------------------------------------------------------------------------
// CALLBACK
// ---------------------------------------------------------------------------
$identity = pg_signin_finish($params);

if (!$identity['ok']) {

    if ($identity['cancelled']) {
        $back = pg_safe_back_url($identity['send_to'], '');

        if ($back === '') {
            $login = get_page_type_url('login');
            $back = ($login !== false && $login !== '') ? (string) $login : PATH;
        }

        go($back);
    }

    output_error($identity['error']);
}

$label = $identity['label'];
$email = $identity['email'];
$send_to = $identity['send_to'];
$signup_mode = $identity['signup'];

// Block list, checked on the address the provider reported, before
// anything is matched, tied or opened.
if (($email !== '') && pg_email_blocked($email)) {
    log_activity(lang(array(
        'string' => '{var:1} sign-in refused because the email address is blocked ({var:2})',
        'vars'   => array($label, $email))), '');
    output_error(lang('This email address cannot be used on this site.'));
}

$user_id = pg_signin_match_user($identity);

if (!$user_id && !$identity['email_verified']) {
    log_activity(lang(array(
        'string' => '{var:1} sign-in refused for an unknown account without a verified e-mail address ({var:2})',
        'vars'   => array($label, ($email !== '') ? $email : $identity['subject']))), '');
    output_error(lang(array(
        'string' => 'Sorry, {var:1} did not confirm the e-mail address of this account, so it cannot be used to sign in here. Please sign in with your account details.',
        'vars'   => $label)));
}

// Nobody matched and this screen may not open an account (the staff login,
// or a site that publishes no entrance page).
if (!$user_id && ($signup_mode === 'none')) {
    log_activity(lang(array(
        'string' => '{var:1} sign-in refused for an unknown account ({var:2})',
        'vars'   => array($label, $email))), '');
    output_error(lang(array(
        'string' => 'There is no account here for that {var:1} address, and new accounts cannot be created from this screen. Please sign in with your account details, or ask the site owner for access.',
        'vars'   => $label)));
}

// Membership: the identity is handed to the membership form, which still
// checks the membership number against the member list. The record is the
// one the Google sign-in leaves, with the provider named.
if (!$user_id && ($signup_mode === 'membership')) {
    $_SESSION['software']['google_membership_pending'] = array(
        'provider'   => $identity['provider'],
        'label'      => $label,
        'sub'        => $identity['subject'],
        'email'      => $email,
        'first_name' => $identity['first_name'],
        'last_name'  => $identity['last_name'],
        'time'       => time(),
    );

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/membership_entrance.php'
        . (($send_to !== '') ? ('?send_to=' . urlencode($send_to)) : ''));
    exit();
}

if (!$user_id) {
    $user_id = pg_signin_create_member($identity);

    if ($user_id) {
        log_activity(lang(array(
            'string' => 'member account opened with {var:1} ({var:2})',
            'vars'   => array($label, $email))), '');
    }
}

if (!$user_id) {
    output_error(lang(array('string' => 'Sorry, we could not sign you in with {var:1}. Please try again.', 'vars' => $label)));
}

$username = db_value("SELECT user_username FROM user WHERE user_id = '" . (int) $user_id . "'");

// A provider is not a second factor: an account that has one (or whose
// role requires one) is asked for it here exactly as after a password.
pg_mfa_gate($user_id, $username, $send_to, true, 'registration_entrance.php');
pg_device_limit_gate($user_id, $username, $send_to, true);

pg_session_sign_in($user_id, $username);

pg_login_set_device_cookie($user_id, true);
setcookie('software[remember_me]', 'true', time() + 315360000, '/');

log_activity(lang(array('string' => 'user signed in with {var:1}', 'vars' => $label)), $username);

require_once(dirname(__FILE__) . '/connect_user_to_order.php');
connect_user_to_order();

$landing = pg_safe_back_url($send_to, '');

if ($landing !== '') {
    go($landing);
}

send_user_to_login_home();
exit();
