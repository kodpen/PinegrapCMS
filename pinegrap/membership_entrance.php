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

$liveform = new liveform('membership_entrance');

// If the user is already logged in and has a user role, then determine if we should send user to login home.
// We don't want to send managers and above to the login home, because we want to allow them to
// view this screen in case they want to edit it.
if (
    (USER_LOGGED_IN == true)
    && (USER_ROLE == 3)
) {
    // Get the folder for the membership entrance page, so we can determine if
    // the user has edit access to the page.
    $query = "SELECT page_folder AS folder_id FROM page WHERE page_type = 'membership entrance'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $folder_id = $row['folder_id'];

    // If there is not a membership entrance page or if the user does not have
    // edit access to the membership entrance page, then send user to login home.
    // We don't want to send users with edit access to the login home,
    // because we want to allow them to view this screen in case they want to edit it.
    if (
        ($folder_id == '')
        || (check_edit_access($folder_id) == false)
    ) {
        send_user_to_login_home();
    }
}

// if the user has not submitted the form yet, then output form
if (!$_POST) {
    print get_membership_entrance_screen();
    $liveform->remove_form('membership_entrance');
    
// else user has completed membership form, so process form
} else {
    validate_token_field();
    
    require_cookies();
    
    $liveform->remove_form('membership_entrance');
    $liveform->add_fields_to_session();
    
    $query_string = '';
    
    // if there is a send to, then add send to to query string
    if ((isset($_POST['send_to']) == true) && ($_POST['send_to'] != '')) {
        $query_string = '?send_to=' . urlencode($_POST['send_to']);
    }
    
    // if the login form was submitted
    if (($_POST['login'] ?? '') == 'true') {
        check_banned_ip_addresses('login');

        $username = $liveform->get_field_value('u');
        // Raw password now; validate_login() checks it against the stored hash.
        $password = $liveform->get_field_value('p');

        // Refuse straight away while this address or account is locked out, so a
        // password list never reaches the credential comparison.
        pg_login_throttle_guard($username);

        // From a few failures on, the attempt must also answer a question; see
        // pg_login_captcha_gate().
        $pg_login_screen = array(
            'action'     => PATH . SOFTWARE_DIRECTORY . '/membership_entrance.php',
            'identifier' => 'u',
            'password'   => 'p',
        );

        pg_login_captcha_gate($username, $liveform, $pg_login_screen);
        
        $liveform->validate_required_field('u', lang('Email or username is required.'));
        $liveform->validate_required_field('p', lang('Password is required.'));
        
        // if there is not already an error, validate login
        if ($liveform->check_form_errors() == false) {
            // if login is not valid, check which part of login is invalid
            $login_user_id = validate_login($username, $password);
            if ($login_user_id === false) {
                // A wrong password is counted before the visitor is told which half was
                // wrong, so the counter cannot be avoided by reading the message.
                pg_login_record_failure($username);

                // if username exists, password is incorrect, so output error about password being incorrect
                if (validate_username($username) == true) {
                    log_activity('access denied (password invalid) (email or username: ' . $username . ')', 'UNKNOWN');
                    
                    $forgot_password_message = '';
                    
                    if (FORGOT_PASSWORD_LINK == true) {
                        $forgot_password_message = lang(' If you have forgotten your password, please click on the forgot password link below.');
                    }
                    
                    $liveform->mark_error('p', lang('The password you entered is incorrect. Please remember that passwords are case sensitive.') . $forgot_password_message);
                    $liveform->assign_field_value('p', '');
                    
                // else username does not exist, so output error about username not existing
                } else {
                    log_activity('access denied (email or username invalid: ' . $username . ')', 'UNKNOWN');
                    $liveform->mark_error('u', lang('The email address or username you entered could not be found. Please register if you have not already done so.'));
                }
            }
        }
        
        // A refused password that brings the account or the address up to the
        // question threshold is answered with the question screen now.
        if (isset($login_user_id) && $login_user_id === false) {
            pg_login_captcha_after_failure($username, $liveform, $pg_login_screen);
        }

        // if an error does not exist, user has logged in successfully, so forward user to next screen
        if ($liveform->check_form_errors() == false) {
            // Signed in: forget this account's failures. Done before $username is
            // replaced below, because the counter was opened under whatever the
            // visitor typed, which may have been their email address.
            pg_login_throttle_pass($username);

            // Get the actual username for the user, because the user probably entered
            // an email address for the username field.  We need the actual username
            // because it is important that we store the actual username in the session and cookies.
            // We already have the verified user id; read the canonical username by id.
            $username = db_value(
                "SELECT user_username FROM user WHERE user_id = '" . (int) $login_user_id . "'");

            // Sign the visitor in and, when the device limit is on, count this device.
            // The gate fires for every login (no-op when the limit is off); a remembered
            // login keeps a persistent cookie, a non-remembered one gets a session cookie
            // only while the limit is on.
            $pg_remember = (REMEMBER_ME == TRUE && $liveform->get_field_value('login_remember_me') == 1);
            pg_device_limit_gate($login_user_id, $username, ($_REQUEST['send_to'] ?? ''), $pg_remember);
            pg_login_set_device_cookie($login_user_id, $pg_remember);

            if (REMEMBER_ME == TRUE) {
                setcookie('software[remember_me]', $pg_remember ? 'true' : 'false', time() + 315360000, '/');
            }

            pg_session_sign_in($login_user_id, $username);

            require_once(dirname(__FILE__) . '/connect_user_to_order.php');
            connect_user_to_order();
            
            // validation complete - we may now continue
            log_activity("user logged into membership area", $username);
            
            // remove liveform because we don't need it anymore
            $liveform->remove_form('membership_entrance');
            
            send_user_to_login_home();
            
        // else an error does exist
        } else {
            // send user back to previous form
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/membership_entrance.php' . $query_string);
        }
        
    // else if the register form was submitted
    } elseif (($_POST['register'] ?? '') == 'true') {
        check_banned_ip_addresses('register as a member');

        // A designed page (the membership widget) names itself in return_to
        // and its widget in pg_widget_id: the widget's contact-bound controls
        // become extra address-book columns, and every answer goes back to
        // that page. The legacy screen sends neither.
        $activate_opts = array();
        $return_to     = '';
        if (isset($_POST['return_to']) && is_scalar($_POST['return_to']) && (string) $_POST['return_to'] !== '') {
            $return_to = pg_safe_redirect_path((string) $_POST['return_to'], '/__none__');
            if ($return_to === '/__none__') {
                $return_to = '';
            }
        }
        if ($return_to !== '' && function_exists('pg_member_widget_register_opts')) {
            $activate_opts = pg_member_widget_register_opts((int) ($_POST['pg_widget_id'] ?? 0), $liveform, 'membership');
        }

        $activated = pg_member_activate($liveform, $activate_opts);

        // if an error does not exist
        if ($activated['ok']) {

            // The designed page: a send_to it chose, else back to itself with
            // the notice its Messages block prints. The legacy screen: the
            // membership confirmation page.
            if ($return_to !== '') {
                $done_url = pg_safe_redirect_path(($_POST['send_to'] ?? ''), '/__none__');
                if ($done_url === '/__none__') {
                    $liveform->add_notice(lang('Your membership is active. You are now signed in.'));
                    $done_url = $return_to;
                }
                header('Location: ' . URL_SCHEME . HOSTNAME . $done_url);
                exit();
            }

            // send user to membership confirmation page
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/membership_confirmation.php' . $query_string);

        // else an error does exist
        } else {
            if ($return_to !== '') {
                header('Location: ' . URL_SCHEME . HOSTNAME . $return_to);
                exit();
            }
            // send user back to previous form
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/membership_entrance.php' . $query_string);
        }
    }
}