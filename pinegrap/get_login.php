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

function get_login($properties = array()) {

    // The default screen (a site without a login page) passes no page.
    $page_id = isset($properties['page_id']) ? $properties['page_id'] : 0;

    $layout_type = get_layout_type($page_id);

    $form = new liveform('login');
    
    // if software is in secure mode, then make sure that form is submitted to a secure URL
    if (URL_SCHEME == 'https://') {
        $action_url = 'https://' . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/index.php';
        
    // else software is not in secure mode, so just use relative URL
    } else {
        $action_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/index.php';
    }

    // If the form has not been submitted yet, then prefill values.
    if (!$form->field_in_session('token')) {
        // If the remember me feature is enabled, then deal with it.
        if (REMEMBER_ME) {
            // If the visitor checked remember me during the last login,
            // then check the remember me check box by default
            if ((($_COOKIE['software']['remember_me'] ?? '')) == 'true') {
                $form->assign_field_value('remember_me', '1');
            }
        }
    }

    // What the default screen and the system layout share: the forgot
    // password link back to where the visitor was going, and which field
    // takes the focus.
    $output_send_to = '';
    if (FORGOT_PASSWORD_LINK == true) {
        // If there is a send to, then prepare it.
        if ((isset($_GET['send_to']) == TRUE) && (($_GET['send_to'] ?? '') != '')) {
            $output_send_to = h(urlencode(($_GET['send_to'] ?? '')));

        // Otherwise there is not a send to, so create send to based on the screen that the visitor is currently on.
        } else {
            $output_send_to = h(urlencode(get_request_uri()));
        }
    }

    // if email field has an error or if password field does not have an error, email field gets focus
    if (($form->check_field_error('email') == true) || ($form->check_field_error('password') == false)) {
        $email_autofocus = true;
        $password_autofocus = false;

    // else password field gets focus
    } else {
        $email_autofocus = false;
        $password_autofocus = true;
    }

    // No login page at all: the default screen, a card like the two-step
    // verification screen (mfa.php) inside output_header_secure()'s
    // Bootstrap. index.php takes an email address or a username in the
    // "email" field, so the field is plain text.
    if ((int)$page_id <= 0) {

        $output_remember_me_checkbox = '';
        if (REMEMBER_ME == TRUE) {
            $output_remember_me_checkbox =
                '<div class="form-check mb-3">
                    ' . $form->output_field(array('type' => 'checkbox', 'name' => 'remember_me', 'id' => 'remember_me', 'value' => '1', 'class' => 'form-check-input')) . '
                    <label class="form-check-label" for="remember_me">' . lang('Remember Me') . '</label>
                </div>';
        }

        $output_forgot_password_link = '';
        if (FORGOT_PASSWORD_LINK == true) {
            $output_forgot_password_link = '<a class="text-center" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/forgot_password.php?send_to=' . $output_send_to . '">' . lang('Forgot password?') . '</a>';
        }

        $output_google_signin = pg_google_signin_button(($_GET['send_to'] ?? ''), false);

        $output_links = '';
        if (($output_forgot_password_link != '') || ($output_google_signin != '')) {
            $output_links =
                '<div class="d-flex flex-column gap-2 mt-3">
                    ' . $output_forgot_password_link . '
                    ' . $output_google_signin . '
                </div>';
        }

        $output =
            '<main class="container py-4 py-md-5">
                <div class="card mx-auto shadow-sm" style="max-width: 28rem;">
                    <div class="card-body p-4">
                        <h1 class="h4 mb-2"><i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>' . lang('Login') . '</h1>
                        <p class="text-muted mb-3">' . lang('Sign in with your email address or username.') . '</p>
                        ' . $form->output_errors() . $form->output_notices() . '
                        <form name="login" action="' . $action_url . '" method="post">
                            ' . get_token_field() . '
                            <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                            <input type="hidden" name="require_cookies" value="true" />
                            <div class="mb-3">
                                <label for="email" class="form-label">' . lang('Email or username') . '</label>
                                ' . $form->field(array(
                                    'type' => 'text',
                                    'name' => 'email',
                                    'id' => 'email',
                                    'class' => 'form-control form-control-lg',
                                    'required' => 'true',
                                    'autocomplete' => 'username',
                                    'spellcheck' => 'false',
                                    'autofocus' => $email_autofocus)) . '
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">' . lang('Password') . '</label>
                                ' . $form->field(array(
                                    'type' => 'password',
                                    'name' => 'password',
                                    'id' => 'password',
                                    'class' => 'form-control form-control-lg',
                                    'required' => 'true',
                                    'autocomplete' => 'current-password',
                                    'spellcheck' => 'false',
                                    'autofocus' => $password_autofocus)) . '
                            </div>
                            ' . $output_remember_me_checkbox . '
                            <button type="submit" name="submit_login" value="Login" class="btn btn-primary w-100">' . lang('Login') . '</button>
                        </form>
                        ' . $output_links . '
                    </div>
                </div>
            </main>';

    // A login page on the system layout.
    } elseif ($layout_type == 'system' || !$layout_type) {

        $output_remember_me_checkbox = '';
        $output_forgot_password_link = '';
    
        // if the remember me feature is enabled, then output remember me row
        if (REMEMBER_ME == TRUE) {
            $output_remember_me_checkbox = 
                    '<tr>
                        <td class="mobile_hide">&nbsp;</td>
                        <td>' . $form->output_field(array('type'=>'checkbox', 'name'=>'remember_me', 'id'=>'remember_me', 'value'=>'1', 'class'=>'software_input_checkbox')) . '<label for="remember_me"> ' . lang('Remember Me') . '</label></td>
                    </tr>';
        }
        
        if (FORGOT_PASSWORD_LINK == true) {
            $output_forgot_password_link = '<a class="forgot_button" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/forgot_password.php?send_to=' . $output_send_to . '">' . lang('Forgot password?') . '</a><br />';
        }
        
        // Notices too, not only errors: a password change that ends without a
        // session leaves its confirmation on this form.
        $output =
            $form->output_errors() . $form->output_notices() . '
            <form name="login" action="' . $action_url . '" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                <input type="hidden" name="require_cookies" value="true" />
                <table style="margin-bottom: 1em">
                    <tr>
                        <td><label for="email">' . lang('Email') . ':</label></td>
                        <td>' .
                            $form->field(array(
                                'type' => 'email',
                                'id' => 'email',
                                'name' => 'email',
                                'size' => '30',
                                'class' => 'software_input_text',
                                'required' => 'true',
                                'autocomplete' => 'email',
                                'autofocus' => $email_autofocus,
                                'spellcheck' => 'false')) . '
                        </td>
                    </tr>
                    <tr>
                        <td><label for="password">' . lang('Password') . ':</label></td>
                        <td>' .
                            $form->field(array(
                                'type' => 'password',
                                'id' => 'password',
                                'name' => 'password',
                                'size' => '30',
                                'class' => 'software_input_password',
                                'required' => 'true',
                                'autocomplete' => 'current-password',
                                'autofocus' => $password_autofocus,
                                'spellcheck' => 'false')) . '
                        </td>
                    </tr>
                    ' . $output_remember_me_checkbox . '
                </table>
                <button type="submit" name="submit_login" value="Login" class="software_input_submit_primary login_button">' . lang('Login') . '</button>
                <br />
                <br />
                ' . $output_forgot_password_link . '
                ' . pg_google_signin_button(($_GET['send_to'] ?? ''), false) . '
            </form>';

    // Otherwise the layout is custom.
    } else {

        $attributes =
            'action="' . $action_url . '" ' .
            'method="post"';

        $form->set('email', 'required', true);
        $form->set('password', 'required', true);

        // If email field has an error or if password field does not have an error,
        // then focus username field.
        if (($form->check_field_error('email')) || (!$form->check_field_error('password'))) {
            $form->set('email', 'autofocus', true);

        // Otherwise focus password field.
        } else {
            $form->set('password', 'autofocus', true);
        }

        $system =
            get_token_field() . '
            <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '">
            <input type="hidden" name="require_cookies" value="true">';

        if (FORGOT_PASSWORD_LINK) {
            $forgot_password_url = PATH . SOFTWARE_DIRECTORY . '/forgot_password.php?send_to=';

            if (($_GET['send_to'] ?? '') != '') {
                $forgot_password_url .= urlencode(($_GET['send_to'] ?? ''));
            } else {
                $forgot_password_url .= urlencode(REQUEST_URL);
            }

        } else {
            $forgot_password_url = '';
        }

        $output = render_layout(array(
            'page_id' => $page_id,
            'messages' => $form->get_messages(),
            'form' => $form,
            'attributes' => $attributes,
            'system' => $system,
            'forgot_password_url' => $forgot_password_url,
            'google_signin' => pg_google_signin_button(($_GET['send_to'] ?? ''), false)));

        $output = $form->prepare($output);
        
    }

    $form->remove_form();

    return $output;
}