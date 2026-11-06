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

$default_inactivity_time = 30;
$default_dialog_time = 15;
$default_dialog_message = lang('Do you want to continue?');
$default_continue_button_label = lang('Continue');
$default_logout_button_label = lang('Exit');

// The session token, read as logout.php reads it: a link planted on a page
// elsewhere does not carry it, so it can neither sign a user out nor put a
// signed-in session into kiosk mode (whose timer signs out).
$session_token = (string) ($_SESSION['software']['token'] ?? '');
$request_token = (isset($_REQUEST['token']) && is_string($_REQUEST['token'])) ? $_REQUEST['token'] : '';
$token_ok = ($session_token !== '') && hash_equals($session_token, $request_token);
$kiosk_on = (($_SESSION['software']['kiosk']['enabled'] ?? '') == true);

switch ($_GET['action'] ?? '') {
    default:
        // A signed-in session is asked first; the form repeats the link's
        // settings with the token. A kiosk normally starts signed out, and its
        // own logout comes back here with a fresh session.
        if (!$token_ok && pg_session_signed_in()) {
            $output_fields = '';
            foreach (array('url', 'inactivity_time', 'dialog_time', 'dialog_message', 'continue_button_label', 'logout_button_label') as $name) {
                if (is_string($_GET[$name] ?? null) && ($_GET[$name] !== '')) {
                    $output_fields .= '<input type="hidden" name="' . $name . '" value="' . h($_GET[$name]) . '">';
                }
            }

            print output_header_secure(array('title' => lang('Kiosk mode'), 'icon' => 'account')) . '
                <div class="container py-12">
                    <div class="row justify-content-center">
                        <div class="col-12 md:col-6 lg:col-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <p>' . lang('Turn on kiosk mode in this browser? You will be signed out after a time without activity.') . '</p>
                                    <form method="get" action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/kiosk.php">
                                        ' . $output_fields . '
                                        <input type="hidden" name="token" value="' . h($session_token) . '">
                                        <button type="submit" class="btn btn-solid theme-primary">' . lang('Turn on kiosk mode') . '</button>
                                        <a href="javascript:history.go(-1)" class="btn btn-outline theme-secondary">' . lang('Cancel') . '</a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>' . output_footer_secure();
            exit();
        }

        // Only accept a same-origin redirect target. pg_safe_back_url()
        // passes relative paths and absolute URLs on this host, and rejects
        // off-site hosts, protocol-relative "//host" and javascript:/data:
        // schemes - closing the open redirect while keeping every legitimate
        // in-site kiosk target working. An empty or rejected value becomes '',
        // which the redirect below turns into go(PATH): the original no-url path.
        $kiosk_url = pg_safe_back_url(($_GET['url'] ?? ''), '');

        $_SESSION['software']['kiosk']['enabled'] = true;
        $_SESSION['software']['kiosk']['activity'] = false;
        $_SESSION['software']['kiosk']['url'] = $kiosk_url;

        // If an inactivity time was passed in the query string,
        // and the value is a positive integer, then store the value in the session.
        if (
            (($_GET['inactivity_time'] ?? '') != '')
            && (is_numeric($_GET['inactivity_time']) == true)
            && ($_GET['inactivity_time'] > 0)
            && ($_GET['inactivity_time'] == round($_GET['inactivity_time']))
        ) {
            $_SESSION['software']['kiosk']['inactivity_time'] = $_GET['inactivity_time'];

        // Otherwise store default value in session.
        } else {
            $_SESSION['software']['kiosk']['inactivity_time'] = $default_inactivity_time;
        }

        // If a dialog time was passed in the query string,
        // and the value is a positive integer, then store the value in the session.
        if (
            (($_GET['dialog_time'] ?? '') != '')
            && (is_numeric($_GET['dialog_time']) == true)
            && ($_GET['dialog_time'] > 0)
            && ($_GET['dialog_time'] == round($_GET['dialog_time']))
        ) {
            $_SESSION['software']['kiosk']['dialog_time'] = $_GET['dialog_time'];

        // Otherwise store default value in session.
        } else {
            $_SESSION['software']['kiosk']['dialog_time'] = $default_dialog_time;
        }

        // If a dialog message was passed in the query string, then store value in session.
        // Only a string is kept: escape_javascript() returns an array unchanged, so an
        // array (?dialog_message[]=x) printed "Array" on every page of the session.
        if (is_string($_GET['dialog_message'] ?? null) && ($_GET['dialog_message'] !== '')) {
            $_SESSION['software']['kiosk']['dialog_message'] = $_GET['dialog_message'];

        // Otherwise store default value in session.
        } else {
            $_SESSION['software']['kiosk']['dialog_message'] = $default_dialog_message;
        }

        // If a continue button label was passed in the query string, then store value in session.
        if (is_string($_GET['continue_button_label'] ?? null) && ($_GET['continue_button_label'] !== '')) {
            $_SESSION['software']['kiosk']['continue_button_label'] = $_GET['continue_button_label'];
            
        // Otherwise store default value in session.
        } else {
            $_SESSION['software']['kiosk']['continue_button_label'] = $default_continue_button_label;
        }

        // If a logout button label was passed in the query string, then store value in session.
        if (is_string($_GET['logout_button_label'] ?? null) && ($_GET['logout_button_label'] !== '')) {
            $_SESSION['software']['kiosk']['logout_button_label'] = $_GET['logout_button_label'];
            
        // Otherwise store default value in session.
        } else {
            $_SESSION['software']['kiosk']['logout_button_label'] = $default_logout_button_label;
        }

        if ($kiosk_url != '') {
            go($kiosk_url);
        } else {
            go(PATH);
        }

        break;

    case 'update_activity':
        $_SESSION['software']['kiosk']['activity'] = true;
        exit();

        break;

    case 'logout':
        // Only a kiosk session is restarted here, and a signed-in one only
        // with the token (the kiosk script sends it). Anything else is the
        // ordinary logout, which asks first when the token is missing.
        if (!$kiosk_on || (!$token_ok && pg_session_signed_in())) {
            go(PATH . SOFTWARE_DIRECTORY . '/logout.php');
        }

        // Remember kiosk properties before we clear session, so we can re-init session
        // with those values after we are done clearing the session.
        $url = ($_SESSION['software']['kiosk']['url'] ?? '');
        $inactivity_time = ($_SESSION['software']['kiosk']['inactivity_time'] ?? '');
        $dialog_time = ($_SESSION['software']['kiosk']['dialog_time'] ?? '');
        $dialog_message = ($_SESSION['software']['kiosk']['dialog_message'] ?? '');
        $continue_button_label = ($_SESSION['software']['kiosk']['continue_button_label'] ?? '');
        $logout_button_label = ($_SESSION['software']['kiosk']['logout_button_label'] ?? '');

        // The kiosk is shared, so the next customer starts without what this
        // one left in the browser beside the session: the guest wishlist
        // (shown to the next visitor and merged into the next account signed
        // in here) and the partner and tracking codes this visitor arrived
        // with (credited on the next customer's order).
        pg_wishlist_cookie_clear();
        setcookie('software[affiliate_code]', '', time() - 1000, '/');
        setcookie('software[tracking_code]', '', time() - 1000, '/');
        unset($_COOKIE['software']['affiliate_code'], $_COOKIE['software']['tracking_code']);

        // If kiosk user is logged into a user account, then logout from that user account.
        if (!empty($_SESSION['sessionusername'])) {
            logout();

        // Otherwise the kiosk user is not logged into a user account, so just clear session.
        } else {
            session_unset();
            session_destroy();
            pg_session_end_clear_cache();
        }

        $parameters = array();

        // If we know where we should send the visitor, then include URL parameter for that.
        if ($url != '') {
            $parameters['url'] = $url;
        }

        // If inactivity time is not the default, then include URL parameter for that.
        if ($inactivity_time != $default_inactivity_time) {
            $parameters['inactivity_time'] = $inactivity_time;
        }

        // If dialog time is not the default, then include URL parameter for that.
        if ($dialog_time != $default_dialog_time) {
            $parameters['dialog_time'] = $dialog_time;
        }

        // If dialog message is not the default, then include URL parameter for that.
        if ($dialog_message != $default_dialog_message) {
            $parameters['dialog_message'] = $dialog_message;
        }

        // If continue button label is not the default, then include URL parameter for that.
        if ($continue_button_label != $default_continue_button_label) {
            $parameters['continue_button_label'] = $continue_button_label;
        }

        // If logout button label is not the default, then include URL parameter for that.
        if ($logout_button_label != $default_logout_button_label) {
            $parameters['logout_button_label'] = $logout_button_label;
        }

        $init_url = build_url(array(
            'url' => PATH . SOFTWARE_DIRECTORY . '/kiosk.php',
            'parameters' => $parameters));

        // Send visitor to kiosk initialization script.
        go($init_url);

        break;
}
?>