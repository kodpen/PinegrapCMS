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

// the following constant is used by init.php in order to determine if a request for index.php
// is the index.php in the software root or the software directory, in order to determine path
define('NON_ROOT_INDEX', TRUE);

include('init.php');

$liveform = new liveform('login');

// Database behind the code (files just landed, upgrade not run yet): the
// upgrade comes first. Signing in here would run on tables the upgrade has
// not created - see pg_require_current_schema().
pg_require_current_schema();

// A sign-in is a posted form. The login screen, the login widget and the
// question screen all post, and reading the credentials from the query string
// as well put passwords into access logs, proxies and browser history. A GET
// with credentials in it is treated as a visit to the empty form.
$pg_login_posted = (isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] === 'POST'));

// If the request used the old u & p field names, then store those values in new field names.
// We do this for backwards compatibility reasons so if someone has a customized form that
// posts to this script, then their old system will continue to work. $_REQUEST is filled as
// well because the form library reads the submitted fields from there.
if (
    $pg_login_posted
    and isset($_POST['u'])
    and isset($_POST['p'])
    and !isset($_POST['email'])
    and !isset($_POST['password'])
) {
    $_POST['email'] = $_POST['u'];
    $_POST['password'] = $_POST['p'];
    $_REQUEST['email'] = $_POST['u'];
    $_REQUEST['password'] = $_POST['p'];
}

// if the user has not submitted the form yet, then output form
if (!$pg_login_posted || !isset($_POST['email'])) {
    // If there is not an error and
    // if the user is already logged in, then send user to login home.
    // For the registration entrance and membership entrance scripts, we allow the
    // form to be shown again for a user with edit access so the user can see what the screen looks like,
    // when the user is logged in, however we have decided not to do that for this script,
    // because many people might currently go to /[SOFTWARE_DIRECTORY]/ as a shortcut
    // to get into the backed of the system when they are already logged in.
    if (
        ($liveform->check_form_errors() == FALSE)
        && pg_session_signed_in()
    ) {
        send_user_to_login_home();
    }

    echo get_login_screen();
    $liveform->remove();
    
// else user has completed login form
} else {

    validate_token_field();
    
    require_cookies();

    check_banned_ip_addresses('login');
    
    $liveform->remove();
    $liveform->add_fields_to_session();
    
    $username = $liveform->get_field_value('email');
    // Raw password now; validate_login() checks it against the stored hash.
    $password = $liveform->get_field_value('password');

    // Refuse straight away while this address or account is locked out, so a
    // password list never reaches the credential comparison.
    pg_login_throttle_guard($username);

    // From a few failures on, the attempt must also answer a question; see
    // pg_login_captcha_gate(). The request ends on the question screen when
    // it owes an answer.
    $pg_login_screen = array(
        'action'     => PATH . SOFTWARE_DIRECTORY . '/index.php',
        'identifier' => 'email',
        'password'   => 'password',
    );

    pg_login_captcha_gate($username, $liveform, $pg_login_screen);
    
    $liveform->validate_required_field('email', lang('Email or username is required.'));
    $liveform->validate_required_field('password', lang('Password is required.'));
    
    // if there is not already an error, validate login
    if ($liveform->check_form_errors() == false) {
        // if login is not valid, check which part of login is invalid
        $login_user_id = validate_login($username, $password);
        if ($login_user_id === false) {
            // A wrong password is counted before the visitor is told which half was
            // wrong, so the counter cannot be avoided by reading the message.
            pg_login_record_failure($username);

            // If email/username exists, password is incorrect, so tell visitor that
            if (validate_username($username) == true) {
                log_activity(lang(array('string'=>'access denied (password invalid) (email or username: {var:1})','vars'=>$username)), lang('UNKNOWN') );
                    

                $forgot_password_message = '';

                if (FORGOT_PASSWORD_LINK == true) {
                    $forgot_password_message = lang(' If you have forgotten your password, please click on the forgot password link below.');
                }
                
                $liveform->mark_error('password', lang('The password you entered is incorrect. Please remember that passwords are case sensitive.') . $forgot_password_message);
                $liveform->assign_field_value('email', $username);
                $liveform->assign_field_value('password', '');
                
            // Otherwise email/username does not exist, so tell visitor that
            } else {
                
                log_activity(lang(array('string'=>'access denied (email or username invalid: {var:1})','vars'=>$username)), lang('UNKNOWN') );

                // If there is an "@" in the email/username, then the visitor probably attempted to
                // enter an email address, so customize message for that.
                if (mb_strpos($username, '@') !== false) {
                    $liveform->mark_error('email',
                        lang('The email address you entered could not be found.') );

                // Otherwise visitor probably tried to enter a username, so customize message.
                } else {
                    $liveform->mark_error('email',
                        lang('The username you entered could not be found. You might try entering an email address instead.') );
                }
            }
        }
    }
    
    // A refused password that brings the account or the address up to the
    // question threshold is answered with the question screen now, rather
    // than a bounce to the plain form that would only stop the visitor again.
    if (isset($login_user_id) && $login_user_id === false) {
        pg_login_captcha_after_failure($username, $liveform, $pg_login_screen);
    }

    // if there is an error with the form, then send user back to form
    if ($liveform->check_form_errors() == true) {
        // A designed login page (the login_form widget) names itself in
        // return_to: the refused attempt goes back to the form the visitor
        // filled in, where its Messages block prints this liveform's errors.
        // send_to stays what it is - where a successful sign-in lands.
        if (isset($_POST['return_to']) && is_scalar($_POST['return_to']) && (string) $_POST['return_to'] !== '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path((string) $_POST['return_to']));
            exit();
        }
        // if this post came from a login_region, send user to send to
        if ((isset($_POST['login_region'])) && ($_POST['login_region'] == 'true')) {
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_POST['send_to'] ?? '')));
            exit();
        } else {
            // send user back to standard login page
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/');
            exit();
        }
    }

    // Signed in: forget this account's failures. Done before $username is
    // replaced below, because the counter was opened under whatever the
    // visitor typed, which may have been their email address.
    pg_login_throttle_pass($username);

    // Get the actual username for the user, because the user probably entered
    // an email address for the username field.  We need the actual username
    // because it is important that we store the actual username in the session and cookies.
    // We already have the verified user id; read the canonical username by id
    // (the visitor may have typed their email address into the username field).
    $username = db_value(
        "SELECT user_username FROM user WHERE user_id = '" . (int) $login_user_id . "'");
    
    // Sign the visitor in and, when the device limit is on, count this device.
    // The gate fires for every login (no-op when the limit is off); a remembered
    // login keeps a persistent cookie, a non-remembered one gets a session cookie
    // only while the limit is on.
    $pg_remember = (REMEMBER_ME == TRUE && $liveform->get_field_value('remember_me') == 1);
    pg_device_limit_gate($login_user_id, $username, ($_REQUEST['send_to'] ?? ''), $pg_remember);
    pg_login_set_device_cookie($login_user_id, $pg_remember);

    if (REMEMBER_ME == TRUE) {
        setcookie('software[remember_me]', $pg_remember ? 'true' : 'false', time() + 315360000, '/');
    }

    $_SESSION['sessionuserid']  = $login_user_id;
    $_SESSION['sessionusername'] = $username;

    require_once(dirname(__FILE__) . '/connect_user_to_order.php');
    connect_user_to_order();
    
    $liveform->remove();
    
    send_user_to_login_home();
}