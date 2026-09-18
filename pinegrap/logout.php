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

// If kiosk mode is enabled, then do a special kiosk logout.
if (($_SESSION['software']['kiosk']['enabled'] ?? '') == true) {
    go(PATH . SOFTWARE_DIRECTORY . '/kiosk.php?action=logout');
}

// A signed-in user is logged out at once only when the request carries the
// session token; the panel menu, the member widget and the logout page type
// all append one. A bare GET, such as a link planted on a page elsewhere,
// first asks for confirmation through a POST form that carries the token, so
// a third party cannot end the session from outside. A visitor who is not
// signed in falls through to logout(), which reports that as before.
$session_token = (string) ($_SESSION['software']['token'] ?? '');
$request_token = (isset($_REQUEST['token']) && is_string($_REQUEST['token'])) ? $_REQUEST['token'] : '';
if ((($session_token === '') || (!hash_equals($session_token, $request_token))) && (pg_session_signed_in() == true)) {
    $send_to = '';
    if (($_REQUEST['send_to'] ?? '') != '') {
        $send_to = pg_safe_redirect_path(($_REQUEST['send_to'] ?? ''));
    }

    print output_header_secure(array('title' => lang('Logout'), 'icon' => 'account')) . '
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <p>' . lang('Do you want to log out?') . '</p>
                            <form method="post" action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/logout.php">
                                ' . get_token_field() . '
                                <input type="hidden" name="send_to" value="' . h($send_to) . '">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-right me-1"></i>' . lang('Logout') . '</button>
                                <a href="javascript:history.go(-1)" class="btn btn-outline-secondary">' . lang('Cancel') . '</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>' . output_footer_secure();
    exit();
}

logout();

// if there is a send to value, then send the user to that page
if (($_REQUEST['send_to'] ?? '') != '') {
    $send_to = pg_safe_redirect_path(($_REQUEST['send_to'] ?? ''));
    header('Location: ' . URL_SCHEME . HOSTNAME . $send_to . (strpos($send_to, '?') === false ? '?' : '&') . 'logged_out=true');

// else, print a default logout page
} else {
    // Start the session again because we killed it when we logged out above,
    // and we are going to initialize some session values below.
    session_start();

    // we need to initialize the device type now because the logout function that
    // was called above clears session and device type cookie, and we want to show
    // the correct version of the logout screen
    initialize_device_type();

    // We need to initialize the token in order to have the token available on the screen that is outputted now.
    initialize_token();

    require_once(dirname(__FILE__) . '/get_logout_screen_content.php');
    
    print get_logout_screen(get_logout_screen_content());
}
?>