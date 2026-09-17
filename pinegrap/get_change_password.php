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

function get_change_password($properties) {

    $page_id = $properties['page_id'];

    $form = new liveform('change_password');

    $attributes =
        'action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/change_password.php" ' .
        'method="post"';

    // If the form is empty, then set default values.
    if ($form->is_empty()) {
        if (USER_LOGGED_IN) {
            $form->set('email_address', USER_EMAIL_ADDRESS);
        }
    }

    $form->set('email_address', 'required', true);
    $form->set('email_address', 'maxlength', 100);

    // A Google-only account (algo 3) has no password yet: the screen becomes
    // "set a password" - no current-password field, and change_password.php
    // skips the current-password check for the session's own account.
    $password_not_set = false;
    if (USER_LOGGED_IN) {
        $password_not_set = ((int) db_value("SELECT user_password_algo FROM user WHERE user_id = '" . (int) USER_ID . "'") === 3);
    }

    if (!$password_not_set) {
        $form->set('current_password', 'required', true);
    }

    // If strong password is enabled, then display password requirements.
    if (STRONG_PASSWORD) {
        $strong_password_help = get_strong_password_requirements();
    } else {
        $strong_password_help = '';
    }

    $form->set('new_password', 'required', true);
    $form->set('new_password_verify', 'required', true);

    if (PASSWORD_HINT) {
        $form->set('password_hint', 'maxlength', 100);
    }

    $my_account_url = '';

    // If the user is logged in then get my account URL, for cancel button.
    if (USER_LOGGED_IN) {
        $my_account_url = get_page_type_url('my account');
    }

    $system = get_token_field();

    $output = render_layout(array(
        'page_id' => $page_id,
        'messages' => $form->get_messages(),
        'form' => $form,
        'attributes' => $attributes,
        'strong_password_help' => $strong_password_help,
        'my_account_url' => $my_account_url,
        'password_not_set' => $password_not_set,
        'system' => $system));

    $output = $form->prepare($output);

    $form->remove();

    return
        '<div class="software_change_password">
            ' . $output . '
        </div>';

}