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

$login_form = new liveform('login');
$register_form = new liveform('register');

// If the user is already logged in and has a user role, then determine if we should send user to login home.
// We don't want to send managers and above to the login home, because we want to allow them to
// view this screen in case they want to edit it.
if (
    (USER_LOGGED_IN == true)
    && (USER_ROLE == 3)
) { 
    // Get the folder for the registration entrance page, so we can determine if
    // the user has edit access to the page.
    $query = "SELECT page_folder AS folder_id FROM page WHERE page_type = 'registration entrance'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $folder_id = $row['folder_id'];

    // If there is not a registration entrance page or if the user does not have
    // edit access to the registration entrance page, then send user to login home.
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
    echo get_registration_entrance_screen();
    $login_form->remove();
    $register_form->remove();
    
// else user has completed registration form, so process form
} else {
    validate_token_field();

    require_cookies();
    
    $query_string = '';
    
    // prepare to add guest value to query string
    if ((($_POST['allow_guest'] ?? '') == "true") || (($_GET['allow_guest'] ?? '') == "true")) {
        $query_string = '?allow_guest=true';
    }
    
    // if there is a send to, then add send to to query string
    if ((isset($_POST['send_to']) == true) && ($_POST['send_to'] != '')) {
        // if query string is blank, then add question mark
        if ($query_string == '') {
            $query_string .= '?';
            
        // else the query string is not blank, so add ampersand
        } else {
            $query_string .= '&';
        }
        
        $query_string .= 'send_to=' . urlencode($_POST['send_to']);
    }
    
    // if the login form was submitted
    if (($_POST['login'] ?? '') == 'true') {

        $login_form->remove();
        $login_form->add_fields_to_session();

        check_banned_ip_addresses('login');

        $username = $login_form->get_field_value('email');
        // Raw password now; validate_login() checks it against the stored hash.
        $password = $login_form->get_field_value('password');

        // Refuse straight away while this address or account is locked out, so a
        // password list never reaches the credential comparison.
        pg_login_throttle_guard($username);

        // From a few failures on, the attempt must also answer a question; see
        // pg_login_captcha_gate().
        $pg_login_screen = array(
            'action'     => PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php',
            'identifier' => 'email',
            'password'   => 'password',
        );

        pg_login_captcha_gate($username, $login_form, $pg_login_screen);
        
        $login_form->validate_required_field('email', lang('Email or username is required.'));
        $login_form->validate_required_field('password', lang('Password is required.'));
        
        // if there is not already an error, validate login
        if ($login_form->check_form_errors() == false) {
            // if login is not valid, check which part of login is invalid
            $login_user_id = validate_login($username, $password);
            if ($login_user_id === false) {
                // A wrong password is counted before the visitor is told which half was
                // wrong, so the counter cannot be avoided by reading the message.
                pg_login_record_failure($username);

                // If email/username exists, password is incorrect, so tell visitor that
                if (validate_username($username) == true) {
                    log_activity(lang(array('string'=>'access denied (password invalid) (email or username: {var:1})','vars'=>$username)), lang('UNKNOWN') );
                    
                    if (FORGOT_PASSWORD_LINK == true) {
                        $forgot_password_message = lang(' If you have forgotten your password, please click on the forgot password link below.');
                    }
                    
                    $login_form->mark_error('password', lang('The password you entered is incorrect. Please remember that passwords are case sensitive.') . $forgot_password_message);
                    $login_form->assign_field_value('password', '');
                    
                // Otherwise email/username does not exist, so tell visitor that
                } else {

                    log_activity(lang(array('string'=>'access denied (email or username invalid: {var:1})','vars'=>$username)), lang('UNKNOWN') );
                        
                        

                    // If there is an "@" in the email/username, then the visitor probably attempted to
                    // enter an email address, so customize message for that.
                    if (mb_strpos($username, '@') !== false) {
                        $login_form->mark_error('email',
                            lang('The email address you entered could not be found. Please register if you have not already done so.') );

                    // Otherwise visitor probably tried to enter a username, so customize message.
                    } else {
                        $login_form->mark_error('email',
                            lang('The username you entered could not be found. You might try entering an email address instead, or register if you have not already done so.') );
                    }
                }
            }
        }
        
        // A refused password that brings the account or the address up to the
        // question threshold is answered with the question screen now.
        if (isset($login_user_id) && $login_user_id === false) {
            pg_login_captcha_after_failure($username, $login_form, $pg_login_screen);
        }

        // if an error does not exist, user has logged in successfully, so forward user to next screen
        if ($login_form->check_form_errors() == false) {
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
            $pg_remember = (REMEMBER_ME == TRUE && $login_form->get_field_value('login_remember_me') == 1);
            pg_device_limit_gate($login_user_id, $username, ($_REQUEST['send_to'] ?? ''), $pg_remember);
            pg_login_set_device_cookie($login_user_id, $pg_remember);

            if (REMEMBER_ME == TRUE) {
                setcookie('software[remember_me]', $pg_remember ? 'true' : 'false', time() + 315360000, '/');
            }

            $_SESSION['sessionuserid']  = $login_user_id;
            $_SESSION['sessionusername'] = $username;

            require_once(dirname(__FILE__) . '/connect_user_to_order.php');
            connect_user_to_order();
            
            // validation complete - we may now continue
            log_activity(lang('user logged into registration area'), $username);
            
            $login_form->remove();
            
            send_user_to_login_home();
            
        // else an error does exist
        } else {
            // send user back to previous form
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php' . $query_string);
        }
        
    // else if the continue as a guest form was submitted
    } elseif (($_POST['continue'] ?? '') == 'true') {
        // Update their session that they are a guest.
        $_SESSION['software']['guest'] = true;
        
        // forward user to send to
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_POST['send_to'] ?? '')));
        
    // else if the register form was submitted
    } elseif (($_POST['register'] ?? '') == 'true') {

        $register_form->remove();
        $register_form->add_fields_to_session();

        check_banned_ip_addresses('register');

        // A designed page (the registration widget) names itself in
        // return_to and its own widget in pg_widget_id: the contact-bound
        // controls of that widget become extra address-book columns, and
        // every answer goes back to that page. The legacy screen sends
        // neither and keeps its own screens.
        $register_opts = array();
        $return_to     = '';
        if (isset($_POST['return_to']) && is_scalar($_POST['return_to']) && (string) $_POST['return_to'] !== '') {
            $return_to = pg_safe_redirect_path((string) $_POST['return_to'], '/__none__');
            if ($return_to === '/__none__') {
                $return_to = '';
            }
        }
        if ($return_to !== '' && function_exists('pg_member_widget_register_opts')) {
            $register_opts = pg_member_widget_register_opts((int) ($_POST['pg_widget_id'] ?? 0), $register_form);
        }

        $registered = pg_member_register($register_form, $register_opts);

        // if an error does not exist
        if ($registered['ok']) {

            $query_string = '';

            // if there is a send to, then add send to to query string
            if ((isset($_POST['send_to']) == true) && ($_POST['send_to'] != '')) {
                $query_string = '?send_to=' . urlencode($_POST['send_to']);
            }

            // The designed page: a send_to it chose, else back to itself with
            // the notice its Messages block prints. The legacy screen: the
            // registration confirmation page.
            if ($return_to !== '') {
                $done_url = pg_safe_redirect_path(($_POST['send_to'] ?? ''), '/__none__');
                if ($done_url === '/__none__') {
                    $register_form->add_notice(lang('Thank you for registering. You are now signed in.'));
                    $done_url = $return_to;
                }
                header('Location: ' . URL_SCHEME . HOSTNAME . $done_url);
                exit();
            }

            // send user to registration confirmation page
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_confirmation.php' . $query_string);

        // else an error does exist
        } else {
            if ($return_to !== '') {
                header('Location: ' . URL_SCHEME . HOSTNAME . $return_to);
                exit();
            }
            // send user back to previous form
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php' . $query_string);
        }
    }
}