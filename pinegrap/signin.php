<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sign in with Google, Microsoft, Apple and the other providers of
 * includes/fn/signin.php. Google sends people back to google_auth.php, which
 * runs this file.
 *
 * One file, two phases:
 *   ?provider=  START:    plant state, nonce and PKCE in the session, send the
 *                         visitor to the provider. With &connect= (a
 *                         "Connect" button of a signed-in account) the
 *                         identity is tied to that account on the way back
 *                         instead of signing anyone in.
 *   state=      CALLBACK: trade the code for an id_token, find the account by
 *                         the identity, else tie one with the same verified
 *                         e-mail, else open one where the screen allows it,
 *                         then sign in. Microsoft answers with a GET, Apple
 *                         posts its answer (form_post).
 *
 * The return address to register with each provider is exactly this file's
 * address with no query, google_auth.php's for Google
 * (pg_signin_redirect_uri(); the security settings show it).
 *
 * A sign-in that does not complete, cancelled ones included, ends on its own
 * screen (pg_signin_fail()) that says why, names the address the provider
 * reported when that is the reason, and leads back to the page the button
 * was on; never on the site's error page, which most sites design as their
 * "page not found".
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

    // google_auth.php names its provider; the old links to it carry none.
    if (defined('PG_SIGNIN_PROVIDER')) {
        $provider_key = PG_SIGNIN_PROVIDER;
    } else {
        $provider_key = (isset($_GET['provider']) && is_string($_GET['provider'])) ? $_GET['provider'] : '';
    }

    $send_to = (isset($_GET['send_to']) && is_string($_GET['send_to'])) ? $_GET['send_to'] : '';
    $create = (isset($_GET['create']) && is_string($_GET['create'])) ? $_GET['create'] : 'create';

    // The page the button was on, for the way back from the failure screen.
    $back = pg_signin_referer_path(
        isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '',
        isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '');

    // A "Connect" button (the account page, the panel's own user screen):
    // the provider is tied to the account signed in now instead of signing
    // anyone in. Checked here, and again when the provider answers.
    $connect_user = 0;
    $connect_panel = false;

    if (isset($_GET['connect']) && is_string($_GET['connect'])) {
        $connect_panel = (isset($_GET['from']) && ($_GET['from'] === 'panel'));
        $connect_user = pg_signin_connect_begin($provider_key, $_GET['connect'], $send_to, $connect_panel);
        $create = '0';
    }

    // A button left on a cached page, or an old link to google_auth.php,
    // after the provider was turned off.
    if (!pg_signin_enabled($provider_key)) {
        $known = pg_signin_providers();

        pg_signin_fail(
            isset($known[$provider_key])
                ? lang(array('string' => 'Sign in with {var:1} is not turned on on this site. Please sign in with your account details.', 'vars' => pg_signin_provider_label($provider_key)))
                : lang('This sign-in is not available.'),
            array('signup' => pg_signin_signup_mode($create), 'back' => $back));
    }

    $start = pg_signin_start($provider_key, $send_to, $create, $back, $connect_user, $connect_panel);

    if (!$start['ok']) {
        pg_signin_fail($start['error'], array('provider' => $provider_key, 'send_to' => $send_to, 'signup' => pg_signin_signup_mode($create), 'back' => $back,
            'connect_user' => $connect_user, 'panel' => $connect_panel));
    }

    // A provider that posts its answer back (Apple's form_post) is a
    // cross-site POST, which a browser sends without a Lax session cookie;
    // the session the state is in would be lost. Same remedy as the payment
    // gateway returns.
    $authorize_params = pg_signin_authorize_params(pg_connect_provider($provider_key));

    if (isset($authorize_params['response_mode']) && ($authorize_params['response_mode'] === 'form_post')) {
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
    pg_signin_fail($identity['error'], $identity);
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
    pg_signin_fail(lang(array(
        'string' => 'You signed in to {var:1} as {var:2}, but this e-mail address cannot be used on this site.',
        'vars'   => array($label, $email))), $identity);
}

// A "Connect": the identity is tied to the account that pressed the button;
// nothing below (matching, opening an account, signing in) applies.
if ($identity['connect_user'] > 0) {
    pg_signin_connect_finish($identity);
}

$user_id = pg_signin_match_user($identity);

if (!$user_id && !$identity['email_verified']) {
    log_activity(lang(array(
        'string' => '{var:1} sign-in refused for an unknown account without a verified e-mail address ({var:2})',
        'vars'   => array($label, ($email !== '') ? $email : $identity['subject']))), '');
    pg_signin_fail(lang(array(
        'string' => 'Sorry, {var:1} did not confirm the e-mail address of this account, so it cannot be used to sign in here. Please sign in with your account details.',
        'vars'   => $label)), $identity);
}

// The verified address belongs to an account here that is tied to another
// person at this provider: it is not taken over, and a second account with
// the same address is not opened beside it.
if (!$user_id && pg_signin_address_user($email)) {
    log_activity(lang(array(
        'string' => '{var:1} sign-in refused: the account with the address {var:2} is connected to another {var:1} account',
        'vars'   => array($label, $email))), '');
    pg_signin_fail(lang(array(
        'string' => 'You signed in to {var:1} as {var:2}. The account on this site with that address is already connected to another {var:1} account, so this one cannot sign in to it. Please sign in with your password, or ask the site owner to disconnect the other {var:1} account from yours.',
        'vars'   => array($label, $email))), $identity);
}

// Nobody matched and this screen may not open an account (the staff login,
// or a site that publishes no entrance page). The address is named: an
// account here with another address is the usual reason.
if (!$user_id && ($signup_mode === 'none')) {
    log_activity(lang(array(
        'string' => '{var:1} sign-in refused for an unknown account ({var:2})',
        'vars'   => array($label, $email))), '');
    pg_signin_fail(lang(array(
        'string' => 'You signed in to {var:1} as {var:2}, but no account on this site uses that address, and accounts cannot be opened from this screen. If your account here has another e-mail address, sign in with your password, or ask the site owner to change the e-mail address of your account to {var:2}.',
        'vars'   => array($label, $email))), $identity);
}

// Membership: the identity is handed to the membership form, which still
// checks the membership number against the member list.
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
    pg_signin_fail(lang(array('string' => 'Sorry, we could not sign you in with {var:1}. Please try again.', 'vars' => $label)), $identity);
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
