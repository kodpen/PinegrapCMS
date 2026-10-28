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

function get_forgot_password($properties = array()) {

    // The default screen (a site without a forgot password page) passes no page.
    $page_id = isset($properties['page_id']) ? $properties['page_id'] : 0;

    $form = new liveform('forgot_password');

    if ($form->get('screen') == 'confirm') {

        $output = $form->get_messages();

        // The default screen keeps its confirmation on the card it was
        // asked from.
        $output = ((int)$page_id <= 0) ? pg_secure_card('bi-key', lang('Forgot Password'), $output) : $output;

    } else {

        $attributes =
            'action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/forgot_password.php" ' .
            'method="post"';

        $form->set('email', 'required', true);
        $form->set('email', 'autofocus', true);

        $screen = $form->get_field_value('screen');

        $password_hint = '';

        if (PASSWORD_HINT && ($screen == 'password_hint')) {
            $email = $form->get_field_value('email');

            $password_hint = db_value(
                "SELECT user_password_hint
                FROM user
                WHERE user_email = '" . e($email) . "'");
        }

        // If a send to was passed in the query string,
        // then set send to.
        if (($_GET['send_to'] ?? '') != '') {
            $form->assign_field_value('send_to', ($_GET['send_to'] ?? ''));

        // Otherwise if there is no send to set in the session,
        // then set send to to home page as a default.
        } else if ($form->get_field_value('send_to') == '') {
            $form->assign_field_value('send_to', PATH);
        }

        // The send to is placed in an anchor href and carried on to the
        // set password screen, so only a same-site path is accepted; a
        // foreign or protocol-relative target falls back to the home page.
        $send_to = pg_safe_redirect_path($form->get_field_value('send_to'), PATH);

        $form->assign_field_value('send_to', $send_to);

        $system =
            get_token_field() . '
            <input type="hidden" name="send_to">
            <input type="hidden" name="screen">';

        if ($password_hint != '') {
            $system .= '<input type="hidden" name="email">';
        }

        // No forgot password page: the default screen, on the sign-in
        // card inside output_header_secure()'s Bootstrap 6. The field
        // names stay those of the system layout, which forgot_password.php
        // reads and prepare() fills.
        if ((int)$page_id <= 0) {

            if ($screen == 'password_hint') {
                $fields =
                    '<p class="mb-3">' . lang('Your Password Hint is:') . ' <strong>' . h($password_hint) . '</strong></p>
                    <p class="mb-5">' . lang('Do you remember your password now?') . '</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="' . h($send_to) . '" class="btn btn-solid theme-primary">' . lang('Yes, I Remember') . '</a>
                        <button type="submit" class="btn btn-outline theme-secondary">' . lang('No, I Forget') . '</button>
                    </div>';

            } else {
                $fields =
                    '<div class="mb-5">
                        <label for="email" class="form-label">' . lang('Email') . '</label>
                        <input type="email" name="email" id="email" class="form-control form-control-lg" autocomplete="email" spellcheck="false">
                    </div>
                    <button type="submit" name="submit" value="Send Email" class="btn btn-solid theme-primary w-100">' . lang('Send Email') . '</button>';
            }

            $output = pg_secure_card('bi-key', lang('Forgot Password'),
                $form->get_messages() . '
                <form ' . $attributes . '>
                    ' . $fields . '
                    ' . $system . '
                </form>');

        } else {
            $output = render_layout(array(
                'page_id' => $page_id,
                'page_type' => 'forgot password',
                'messages' => $form->get_messages(),
                'form' => $form,
                'attributes' => $attributes,
                'screen' => $screen,
                'password_hint' => $password_hint,
                'send_to' => $send_to,
                'system' => $system));
        }

        $output = $form->prepare($output);

    }

    $form->remove();

    return
        '<div class="software_forgot_password">
            ' . $output . '
        </div>';

    return $output;

}