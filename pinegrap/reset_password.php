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
include_once('liveform.class.php');
$liveform_view_users = new liveform('view_users');
$user = validate_user();
validate_area_access($user, 'manager');

validate_token_field();

// Nobody resets his/her own password here.
//
// The reset revokes every remembered session of the account (the
// pg_auth_token_revoke_user() call below), so the editor who pressed the button
// is signed out on the very next request - which is the redirect at the bottom
// of this file, before the screen carrying the new password gets to render the
// notice. From then on the password only exists in the e-mail; where mail is
// not configured it exists nowhere at all, and an administrator doing this to
// his/her own account locks the site's last administrator out of it.
//
// An account's own password is changed from the change password screen, which
// mints a fresh token for the browser making the change and so keeps it signed
// in. The button is not drawn on one's own record (edit_user.php), but a screen
// that does not draw a button is not an access check.
if ((int) ($_POST['user_id'] ?? 0) === (int) $user['id']) {
    log_activity(lang('access denied because a user may not reset his/her own password'), $_SESSION['sessionusername']);
    output_error(lang('Access denied. You may not reset your own password here. Use the change password screen instead.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

// get user information
$query =
    "SELECT
        user_username,
        user_email,
        user_role,
        user_home,
        user_manage_contacts,
        user_manage_emails,
        user_manage_ecommerce,
        manage_ecommerce_reports,
        manage_erp,
        user_manage_forms,
        user_manage_calendars,
        user_manage_visitors
    FROM user
    WHERE user_id = '" . escape($_POST['user_id'] ?? '') . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// if a user could not be found, then output error
if (mysqli_num_rows($result) == 0) {
    output_error(lang('The user could not be found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

$row = mysqli_fetch_assoc($result);

$user_username = $row['user_username'];
$user_email = $row['user_email'];
$user_role = $row['user_role'];
$user_home = $row['user_home'];
$user_manage_contacts = $row['user_manage_contacts'];
$user_manage_emails = $row['user_manage_emails'];
$user_manage_ecommerce = $row['user_manage_ecommerce'];
$manage_ecommerce_reports = $row['manage_ecommerce_reports'];
$manage_erp = $row['manage_erp'] ?? 0;
$user_manage_forms = $row['user_manage_forms'];
$user_manage_calendars = $row['user_manage_calendars'];
$user_manage_visitors = $row['user_manage_visitors'];

// if editor is less than an administrator role
// and the editor's role is less than or equal to the user that the editor is trying to reset the password for,
// output error because user does not have access to do this
if (($user['role'] > 0) && ($user['role'] >= $user_role)) {
    log_activity("access denied because user does not have access to reset password for user", $_SESSION['sessionusername']);
    output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

$random_password = get_random_string(array(
    'type' => 'lowercase_letters',
    'length' => 10));

// insert new random password into database
$query =
    "UPDATE user 
    SET 
        user_password = '" . escape(pg_password_hash($random_password)) . "', 
        user_password_algo = 2,
        user_password_hint = '' 
        " . pg_password_changed_sql() . "
    WHERE user_id = '" . escape($_POST['user_id'] ?? '') . "'";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// The account's password just changed under it: drop every remembered session,
// so a lost or shared "remember me" cookie cannot outlive the reset.
pg_auth_token_revoke_user((int) ($_POST['user_id'] ?? 0));

$login = '';
    
// if user's role is administrator, designer, or manager
// or user has edit rights
// or user has access to control panel
// then set link to PATH/SOFTWARE_DIRECTORY/
if (
    ($user_role < 3)
    || (no_acl_check($_POST['user_id']) == true)
    || ($user_manage_calendars == 'yes')
    || ($user_manage_forms == 'yes')
    || ($user_manage_visitors == 'yes')
    || ($user_manage_contacts == 'yes')
    || ($user_manage_emails == 'yes')
    || ($user_manage_ecommerce == 'yes')
    || $manage_ecommerce_reports
    || $manage_erp
    || (count(get_items_user_can_edit('ad_regions', $_POST['user_id'])) > 0)
) {
    $login = 
        lang('Login') . ':' . "\n" .
        URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . "\n";

// else if there was a send to page selected for this user        
} elseif ($user_home) {
    $login =
        lang('Login') . ':' . "\n" .
        URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . encode_url_path(get_page_name($user_home)) . "\n";
}

email(array(
    'to' => $user_email,
    'from_name' => ORGANIZATION_NAME,
    'from_email_address' => EMAIL_ADDRESS,
    'subject' => lang('Password Reset'),
    'body' =>
        lang('Your password was reset by an administrator. You can find your new password below.') . "\n\n" .
        lang('Email') . ': ' . $user_email . "\n" .
        lang('Password') . ': ' . $random_password . "\n\n" .
        $login . "\n"));
    
log_activity("password was reset for user ($user_username)", $_SESSION['sessionusername']);
$liveform_view_users->add_notice(lang(array('string' => 'The user\'s password has been reset, and a new password, &quot;{var:1}&quot;, has been e-mailed to the user.', 'vars' => array(h($random_password)))));

// If there is a send to value then send user back to that screen
if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
    
// else send user to the default view
} else {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_users.php');
}
exit();