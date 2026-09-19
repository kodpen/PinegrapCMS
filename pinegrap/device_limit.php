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
 *
 * Device-limit confirmation screen.
 *
 * When a sign-in would pass the account's device limit, the login
 * flow stashes a short-lived pending sign-in (pg_device_limit_gate) and sends
 * the visitor here BEFORE anything is signed in. This screen asks whether to
 * sign out the oldest devices and continue. On confirm it revokes the oldest
 * tokens, then completes the sign-in itself; on cancel it signs the visitor in
 * nowhere. The credentials were already verified upstream - the pending record
 * only exists because a correct sign-in was in progress.
 */

include('init.php');

// The pending sign-in the gate planted. Short-lived and single-purpose.
$pending = $_SESSION['software']['device_limit_pending'] ?? null;
$pending_valid = is_array($pending)
    && !empty($pending['user_id'])
    && isset($pending['time'])
    && ((time() - (int) $pending['time']) <= 600);

// No valid pending (never started, expired, or already used): nothing to
// confirm - send the visitor to sign in.
if (!$pending_valid) {
    unset($_SESSION['software']['device_limit_pending']);
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php');
    exit();
}

// Locked devices: the confirmation this screen exists for is not on offer.
// Reachable only if the switch was turned on between the gate and here, or if
// someone kept the URL; either way the answer is the same as the gate's.
if (pg_device_limit_strict()) {
    unset($_SESSION['software']['device_limit_pending']);
    output_error(lang('This account is already signed in on the maximum number of devices. Sign out on one of those devices and try again, or ask the site owner for help.'));
}

$user_id  = (int) $pending['user_id'];
$username = (string) $pending['username'];
$send_to  = (string) ($pending['send_to'] ?? '');
$remember = !empty($pending['remember']);

// A submitted choice.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();
    $choice = isset($_POST['pg_device_action']) ? (string) $_POST['pg_device_action'] : '';

    // Cancel: sign in nowhere, drop the pending record, back to sign in. The
    // strict device limit means an over-limit visitor who declines does not get
    // in until they free a device elsewhere.
    if ($choice !== 'confirm') {
        unset($_SESSION['software']['device_limit_pending']);
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php');
        exit();
    }

    // Confirm: revoke the oldest tokens down to one below the limit, so the new
    // device brings the account exactly to the limit. (If the limit was cleared
    // meanwhile, revoke nothing.)
    $limit = pg_device_limit();
    if ($limit > 0) {
        pg_device_limit_revoke_oldest($user_id, $limit - 1);

        // Nothing could be freed because every slot is pinned: the confirmation
        // was accepted but there is still no room, so the sign-in does not
        // happen. Saying it plainly beats signing a device in over the limit.
        if (pg_device_limit_exceeded($user_id)) {
            unset($_SESSION['software']['device_limit_pending']);
            output_error(lang('The devices on this account were locked by the site owner, so none could be signed out. Please ask the site owner for help.'));
        }
    }

    // Complete the sign-in the same way the login flow would: a persistent
    // cookie for a remembered login, a session cookie otherwise.
    unset($_SESSION['software']['device_limit_pending']);
    pg_session_sign_in($user_id, $username);

    pg_login_set_device_cookie($user_id, $remember);
    if (REMEMBER_ME == TRUE) {
        setcookie('software[remember_me]', $remember ? 'true' : 'false', time() + 315360000, '/');
    }

    require_once(dirname(__FILE__) . '/connect_user_to_order.php');
    connect_user_to_order();

    log_activity(lang('user signed in after clearing older devices'), $username);

    // send_user_to_login_home() reads the session we just set and $_REQUEST for
    // send_to; the confirm form carries send_to as a hidden field.
    send_user_to_login_home();
    exit();
}

// No choice yet: show the confirmation.
$limit    = pg_device_limit();
$active   = pg_auth_token_count($user_id);
$to_close = max(1, $active - $limit + 1);

$content =
    '<div style="max-width:520px;margin:2em auto;padding:1.5em;border:1px solid #dadce0;border-radius:8px;font-family:Roboto,Arial,sans-serif">'
    . '<h2 style="margin-top:0">' . h(lang('Too many signed-in devices')) . '</h2>'
    . '<p>' . h(lang(array(
        'string' => 'This account is signed in on {var:1} devices, and the limit is {var:2}. To continue on this device, the oldest {var:3} will be signed out.',
        'vars'   => array((string) $active, (string) $limit, (string) $to_close)))) . '</p>'
    . '<form method="post" action="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/device_limit.php') . '" style="margin-top:1.25em">'
    . get_token_field()
    . '<input type="hidden" name="send_to" value="' . h($send_to) . '"/>'
    . '<button type="submit" name="pg_device_action" value="confirm" class="software_input_submit_primary" style="margin-right:.5em">'
    . h(lang('Sign out the others and continue')) . '</button>'
    . '<button type="submit" name="pg_device_action" value="cancel" class="software_input_submit_secondary">'
    . h(lang('Cancel')) . '</button>'
    . '</form></div>';

print output_header_secure() . $content . output_footer_secure();
