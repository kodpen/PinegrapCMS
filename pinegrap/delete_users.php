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

// The users list sends its selection as the list table driver keeps it: the
// users picked, or the whole view by its condition less the exceptions. It
// is read back with the list's own query and the user's rights
// (pg_user_list_selected_ids()).
require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');
$user_ids = pg_user_list_selected_ids($user, $_POST['selection'] ?? '', $_POST['list_params'] ?? '');

if ($user_ids === false) {
    output_error(lang('Query failed.'));
}

if ($user_ids === null) {
    output_error(lang('Too many users are selected. Select fewer users and try again.') . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
}

// if at least one user was selected
if ($user_ids) {
    $number_of_users = 0;

    foreach ($user_ids as $user_id) {
        // get user's role so that we can determine if this logged-in user has access to delete this user
        $query = "SELECT user_role FROM user WHERE user_id = ?";
        $result = db_q($query, array((string) $user_id)) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_assoc($result);

        // A user deleted meanwhile (in another tab, by another
        // administrator) is skipped: its missing role would read as an
        // administrator and be counted as deleted.
        if (!$row) {
            continue;
        }

        $user_role = $row['user_role'];
        
        // if logged-in user's role is administrator or if logged-in user's role is above this user's role,
        // then logged-in user has access to delete this user, so continue to delete user
        if (($user['role'] == 0) || ($user['role'] < $user_role)) {
            // The last enabled administrator is not deleted, the same rule as
            // the edit screen (pg_user_is_last_admin()): a disabled
            // administrator cannot sign in, so it does not count as another
            // one. Asked per user, after the deletions before it.
            if (($user_role != 0) || !pg_user_is_last_admin($user_id)) {
                pg_user_delete($user_id);

                $number_of_users++;

            } else {
                $liveform_view_users->mark_error('', lang('A user could not be deleted because it was the only administrator.'));
            }
        }
    }
    
    // if more than one user was deleted, then log activity and add notice
    if ($number_of_users > 0) {
        $users_deleted_text = ($number_of_users == 1)
            ? lang(array('string' => '{var:1} user was deleted', 'vars' => pg_format_number($number_of_users, 0)))
            : lang(array('string' => '{var:1} users were deleted', 'vars' => pg_format_number($number_of_users, 0)));

        log_activity($users_deleted_text, $_SESSION['sessionusername']);
        $liveform_view_users->add_notice($users_deleted_text);
    }
}

// If there is a send to value then send user back to that screen
if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
    
// else send user to the default view
} else {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_users.php');
}
?>