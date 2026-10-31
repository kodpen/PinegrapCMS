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

function get_set_password($properties) {

    $page_id = $properties['page_id'];
    $folder_id = $properties['folder_id'];

    $form = new liveform('set_password');

    if ($form->get('screen') == 'confirm') {

        $output = $form->get_messages();

        // The default screen keeps its confirmation on the card it was
        // asked from.
        $output = ((int)$page_id <= 0) ? pg_secure_card('bi-key', lang('Set Password'), $output) : $output;

    } else {

        $token = $_GET['k'] ?? '';

        // Only a token names an account; without one there is no address to show.
        $user = null;

        // If there is a token in the query string, then validate it.
        if ($token) {

            // Create a hash of the token, because that is what we store in db.
            $token_hash = hash('sha256', $token);

            // Check database if token is valid.
            $user = db_item(
                "SELECT
                    user_id AS id,
                    user_username AS username,
                    user_email AS email,
                    token_timestamp
                FROM user
                WHERE token = '" . e($token_hash) . "'");

            // If query to database return no results for token
            if (!is_array($user) || !$user['id']) {

                log_activity(pg_log_lang(array('string' => 'Set Password: invalid token')));

                output_error(lang(array('string' => 'Sorry, the token is not valid, so we can\'t allow you to set a password. Please check if your email client possibly broke the link. If so, then you might try fixing the link. Otherwise, the token might be old, so please <a href="{var:1}">request a new email</a>.', 'vars' => array(h(pg_sign_in_screen_url('forgot password', 'forgot_password.php'))))));
            }

            $token_timestamp = $user['token_timestamp'];
            $time_24hrs = (24*60*60);

            // Check to see if token has expired after 24 hours
            if (($token_timestamp + $time_24hrs) < time()) {
                
                log_activity(pg_log_lang(array('string' => 'Set Password: expired token')));

                output_error(lang(array('string' => 'Sorry, the token has expired. Please <a href="{var:1}">request a new email</a>.', 'vars' => array(h(pg_sign_in_screen_url('forgot password', 'forgot_password.php'))))));
            }   


        // Otherwise, there is not a token, so if visitor does not have edit access to page, then
        // output error.  We don't output an error for users with edit access, because they need
        // to be able to view page/form and edit it.
        } else if (!check_edit_access($folder_id)) {

            log_activity(pg_log_lang(array('string' => 'Set Password: missing token')));

            output_error(lang('Sorry, the token is missing from the address, so we can\'t allow you to set a password.'));
        }

        $attributes =
            'action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/set_password.php" ' .
            'method="post"';

        // If the form is empty, then set default values.
        if ($form->is_empty()) {
            $form->set('send_to', ($_GET['send_to'] ?? ''));
            $form->set('k', $token);
        }

        // If strong password is enabled, then display password requirements.
        if (STRONG_PASSWORD) {
            $strong_password_help = get_strong_password_requirements();
        } else {
            $strong_password_help = '';
        }

        // The Strength meter only on the built-in screen (no page), drawn in
        // the panel chrome on Bootstrap 6; a site page runs 5.3.8.
        $strength_meter = '';
        if (!$page_id) {
            $strength_meter = pg_password_strength_meter('new_password', STRONG_PASSWORD) . pg_password_strength_script();
        }

        $form->set('new_password', 'required', true);

        if (PASSWORD_HINT) {
            $form->set('password_hint', 'maxlength', 100);
        }

        $system =
            get_token_field() . '
            <input type="hidden" name="send_to">' . '
            <input type="hidden" name="k">';

        // No set password page: the default screen, on the sign-in card
        // inside output_header_secure()'s Bootstrap 6, with the Strength
        // meter under the password. The field names stay those of the
        // system layout, which set_password.php reads and prepare() fills.
        if ((int)$page_id <= 0) {

            $output_email = '';
            if (is_array($user) && ($user['email'] != '')) {
                $output_email = '<p class="fg-secondary mb-5">' . h($user['email']) . '</p>';
            }

            // Password managers take the account from a username field in
            // the same form; without one they save the new password against
            // no account. Hidden from view and from the keyboard, and with no
            // name, so set_password.php receives nothing new.
            $output_username = '';
            if (is_array($user)) {
                $output_username = '<input type="text" id="set_password_username" class="visually-hidden" value="' . h($user['email'] ?: $user['username']) . '" autocomplete="username" readonly tabindex="-1" aria-hidden="true">';
            }

            $output_strong_password_help = '';
            if ($strong_password_help != '') {
                $output_strong_password_help = '<div class="small fg-secondary mb-5">' . $strong_password_help . '</div>';
            }

            $output_password_hint = '';
            if (PASSWORD_HINT) {
                $output_password_hint =
                    '<div class="mb-5">
                        <label for="password_hint" class="form-label">' . lang('Password Hint') . '</label>
                        <input type="text" name="password_hint" id="password_hint" class="form-control">
                    </div>';
            }

            $output = pg_secure_card('bi-key', lang('Set Password'),
                $output_email . $form->get_messages() . '
                <form ' . $attributes . '>
                    ' . $output_username . '
                    ' . $output_strong_password_help . '
                    <div class="mb-5">
                        <label for="new_password" class="form-label">' . lang('Password') . '</label>
                        <input type="password" name="new_password" id="new_password" class="form-control form-control-lg" autocomplete="new-password">
                        ' . $strength_meter . '
                    </div>
                    ' . $output_password_hint . '
                    <button type="submit" name="submit" value="Set Password" class="btn btn-solid theme-primary w-100">' . lang('Set Password') . '</button>
                    ' . $system . '
                </form>');

        } else {
            $output = render_layout(array(
                'page_id' => $page_id,
                'page_type' => 'set password',
                'messages' => $form->get_messages(),
                'form' => $form,
                'attributes' => $attributes,
                'strong_password_help' => $strong_password_help,
                'strength_meter' => $strength_meter,
                'email' => (is_array($user) ? $user['email'] : ''),
                'system' => $system));
        }

        $output = $form->prepare($output);
        
    }

    $form->remove();

    return
        '<div class="software_set_password">
            ' . $output . '
        </div>';
}