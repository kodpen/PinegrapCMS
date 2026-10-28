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
include_once('includes/user_permissions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/user_form.php');
$liveform_view_users = new liveform('view_users');
$user = validate_user();
validate_area_access($user, 'manager');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM user WHERE user_id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// if editor is less than an administrator role, check to make sure that editor has access to edit user
if ($user['role'] > 0) {
    // get role of user that is being edited
    $query =
        "SELECT user_role
        FROM user
        WHERE user_id = ?";
    $result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $role = $row['user_role'];
    
    // if the editor's role is less than or equal to the user that the editor is trying to edit, then output error
    if ($user['role'] >= $role) {
        log_activity(lang('access denied because user does not have access to edit user'), $_SESSION['sessionusername']);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' .  lang('Go back') . '</a>.');
    }
}

// Manual sign-in unlock.
//
// Handled ahead of the edit form's own POST branch because it is not part of
// saving the user: it clears this account's throttle counters and comes
// straight back to the screen, so it must not fall through into the save.
if (isset($_POST['unlock_sign_in'])) {

    validate_token_field();

    $unlock_user = db_item(
        "SELECT user_username, user_email
        FROM user
        WHERE user_id = '" . escape($_POST['id'] ?? '') . "'");

    if ($unlock_user) {

        pg_login_unlock($unlock_user['user_username'], $unlock_user['user_email']);

        log_activity(
            lang(array(
                'string' => 'sign-in lock lifted for {var:1}',
                'vars'   => $unlock_user['user_username'])),
            $_SESSION['sessionusername']);
    }

    $unlock_send_to = '';

    if ((isset($_POST['send_to'])) && ($_POST['send_to'] != '')) {
        $unlock_send_to = '&send_to=' . urlencode($_POST['send_to']);
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY
        . '/edit_user.php?id=' . urlencode($_POST['id'] ?? '') . $unlock_send_to);

    exit();
}

// Disconnect Google from this account.
//
// The link is the Google subject - a permanent id - not the email address.
// That is deliberate: either side's email can change (the member renames their
// Gmail, the operator edits the account's email) and a link that broke on that
// would lock people out of their own accounts. So changing the email here does
// NOT unlink Google, and this button is how the link is actually removed -
// when an account changes hands, or the wrong Google account was connected.
//
// Handled ahead of the edit form's own POST branch, like unlock_sign_in above:
// it is not part of saving the user and must not fall through into the save.
if (isset($_POST['pg_unlink_google']) || isset($_POST['pg_unlink_identity'])) {

    validate_token_field();

    // A provider of signin.php; pg_unlink_google is the older form of
    // pg_unlink_identity=google.
    $unlink_provider = (isset($_POST['pg_unlink_identity']) && is_string($_POST['pg_unlink_identity'])) ? $_POST['pg_unlink_identity'] : 'google';
    $unlink_label = pg_signin_provider_label($unlink_provider);

    $unlink_user_id = (int) ($_POST['id'] ?? 0);
    $unlink_user = db_item(
        "SELECT user_username, user_role, user_password_algo
        FROM user
        WHERE user_id = '" . $unlink_user_id . "'");

    if ($unlink_user) {

        // Same rule as "login as user": a non-administrator may only act on an
        // account with a lower-privileged role, and always on their own.
        if (($unlink_user_id !== (int) USER_ID) && (USER_ROLE > 0) && (USER_ROLE >= (int) $unlink_user['user_role'])) {
            log_activity(lang(array(
                'string' => 'access denied to disconnect {var:1} for a higher-role user ({var:2})',
                'vars'   => array($unlink_label, $unlink_user['user_username']))), USER_USERNAME);
            output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        // An account left with no other way in would be locked out entirely.
        if (!pg_signin_can_remove($unlink_user_id, $unlink_provider)) {
            output_error(lang(array(
                    'string' => 'This account signs in with {var:1} only. Give it a password first, or disconnecting {var:1} would lock it out.',
                    'vars'   => $unlink_label))
                . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        pg_signin_identity_remove($unlink_user_id, $unlink_provider);

        log_activity(lang(array(
            'string' => '{var:1} connection removed from user ({var:2})',
            'vars'   => array($unlink_label, $unlink_user['user_username']))), $_SESSION['sessionusername'] ?? '');
    }

    $unlink_send_to = '';

    if ((isset($_POST['send_to'])) && ($_POST['send_to'] != '')) {
        $unlink_send_to = '&send_to=' . urlencode($_POST['send_to']);
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY
        . '/edit_user.php?id=' . urlencode($_POST['id'] ?? '') . $unlink_send_to);

    exit();
}

// Turn off two-step verification, another person's or one's own: the way
// back in for a person who lost both the authenticator app and the recovery
// codes. Removes the second factor and its codes and signs the account out
// everywhere else; the person signs in with the password alone afterwards
// (and is asked to set it up again if their role requires it). No password
// or code is asked for here: this is already a signed-in panel session, and
// for an administrator who lost the device this screen is the only way out.
// The account page, open to every member, keeps asking for both. Turning it
// on is not offered here: the key has to be confirmed on the person's own
// device. Handled ahead of the save, like the two above.
if (isset($_POST['pg_mfa_reset'])) {

    validate_token_field();

    $mfa_reset_user_id = (int) ($_POST['id'] ?? 0);
    $mfa_reset_self = ($mfa_reset_user_id === (int) USER_ID);
    $mfa_reset_user = db_item(
        "SELECT user_username, user_role
        FROM user
        WHERE user_id = '" . $mfa_reset_user_id . "'");

    if ($mfa_reset_user) {

        // An operator signed in as this person cannot change the person's
        // second factor, here or on the account page.
        if ($mfa_reset_self && !empty($_SESSION['software']['logged_in_as_different_user'])) {
            output_error(lang('Two-step verification cannot be changed while signed in as another user.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        // Same rule as disconnecting Google: one's own account always, another
        // one only below one's own role (administrators: any).
        if (!$mfa_reset_self && (USER_ROLE > 0) && (USER_ROLE >= (int) $mfa_reset_user['user_role'])) {
            log_activity(lang(array(
                'string' => 'access denied to turn off two-step verification for a higher-role user ({var:1})',
                'vars'   => array($mfa_reset_user['user_username']))), USER_USERNAME);
            output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        pg_mfa_reset($mfa_reset_user_id);

        if ($mfa_reset_self) {
            unset($_SESSION['software']['mfa_codes_show']);
            log_activity(lang('user turned off two-step verification'), USER_USERNAME);
        } else {
            log_activity(lang(array(
                'string' => 'two-step verification turned off for user ({var:1})',
                'vars'   => array($mfa_reset_user['user_username']))), $_SESSION['sessionusername'] ?? '');
        }
    }

    $mfa_reset_send_to = '';

    if ((isset($_POST['send_to'])) && ($_POST['send_to'] != '')) {
        $mfa_reset_send_to = '&send_to=' . urlencode($_POST['send_to']);
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY
        . '/edit_user.php?id=' . urlencode($_POST['id'] ?? '') . $mfa_reset_send_to);

    exit();
}

if (!$_POST) {
    $set_page_type_values = array();
    
    $query =
        "SELECT
            user_id,
            user_username,
            user_email,
            user_role,
            user_home,
            user_password_hint,
            user_badge,
            user_badge_label,
            user_reward_points,
            user_manage_contacts,
            user_manage_visitors,
            user_create_pages,
            user_delete_pages,
            user_manage_forms,
            user_manage_calendars,
            user_manage_emails,
            user_manage_ecommerce,
            user_view_card_data,
            manage_ecommerce_reports,
            manage_erp,
            manage_erp_cash,
            manage_erp_settings,
            user_set_offline_payment,
            user_publish_calendar_events,
            user_set_page_type_email_a_friend,
            user_set_page_type_folder_view,
            user_set_page_type_photo_gallery,
            user_set_page_type_custom_form,
            user_set_page_type_custom_form_confirmation,
            user_set_page_type_form_list_view,
            user_set_page_type_form_item_view,
            user_set_page_type_form_view_directory,
            user_set_page_type_calendar_view,
            user_set_page_type_calendar_event_view,
            user_set_page_type_catalog,
            user_set_page_type_catalog_detail,
            user_set_page_type_express_order,
            user_set_page_type_order_form,
            user_set_page_type_shopping_cart,
            user_set_page_type_shipping_address_and_arrival,
            user_set_page_type_shipping_method,
            user_set_page_type_billing_information,
            user_set_page_type_order_preview,
            user_set_page_type_order_receipt,
            contacts.image AS contact_image,
            contacts.file_id AS contact_file_id,
            contacts.id AS contact_id,
            contacts.first_name,
            contacts.last_name,
            contacts.email_address,
            contacts.member_id
        FROM user
        LEFT JOIN contacts ON user.user_contact = contacts.id
        WHERE user_id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $id = $row['user_id'];
    $username = $row['user_username'];
    $email = $row['user_email'];
    $user_identities = pg_signin_identities((int) $id);
    $role = $row['user_role'];
    $home = $row['user_home'];
    $password_hint = $row['user_password_hint'];
    $badge = $row['user_badge'];
    $badge_label = $row['user_badge_label'];
    $reward_points = $row['user_reward_points'];
    $manage_contacts = $row['user_manage_contacts'];
    $manage_visitors = $row['user_manage_visitors'];
    $create_pages = $row['user_create_pages'];
    $delete_pages = $row['user_delete_pages'];
    $manage_forms = $row['user_manage_forms'];
    $manage_calendars = $row['user_manage_calendars'];
    $manage_emails = $row['user_manage_emails'];
    $manage_ecommerce = $row['user_manage_ecommerce'];
    $view_card_data = $row['user_view_card_data'];
    $manage_ecommerce_reports = $row['manage_ecommerce_reports'];
    $manage_erp = $row['manage_erp'] ?? 0;
    $manage_erp_cash = $row['manage_erp_cash'] ?? 0;
    $manage_erp_settings = $row['manage_erp_settings'] ?? 0;
    $manage_erp_readonly = pg_user_has_erp_readonly_column()
        ? (int) db_value("SELECT manage_erp_readonly FROM user WHERE user_id = '" . (int) $_GET['id'] . "' LIMIT 1")
        : 0;
    $ws_rights = pg_user_permission_ws_values((int) $_GET['id']);
    $set_offline_payment = $row['user_set_offline_payment'];
    $publish_calendar_events = $row['user_publish_calendar_events'];
    $set_page_type_values['set_page_type_email_a_friend'] = $row['user_set_page_type_email_a_friend'];
    $set_page_type_values['set_page_type_folder_view'] = $row['user_set_page_type_folder_view'];
    $set_page_type_values['set_page_type_photo_gallery'] = $row['user_set_page_type_photo_gallery'];
    $set_page_type_values['set_page_type_custom_form'] = $row['user_set_page_type_custom_form'];
    $set_page_type_values['set_page_type_custom_form_confirmation'] = $row['user_set_page_type_custom_form_confirmation'];
    $set_page_type_values['set_page_type_form_list_view'] = $row['user_set_page_type_form_list_view'];
    $set_page_type_values['set_page_type_form_item_view'] = $row['user_set_page_type_form_item_view'];
    $set_page_type_values['set_page_type_form_view_directory'] = $row['user_set_page_type_form_view_directory'];
    $set_page_type_values['set_page_type_calendar_view'] = $row['user_set_page_type_calendar_view'];
    $set_page_type_values['set_page_type_calendar_event_view'] = $row['user_set_page_type_calendar_event_view'];
    $set_page_type_values['set_page_type_catalog'] = $row['user_set_page_type_catalog'];
    $set_page_type_values['set_page_type_catalog_detail'] = $row['user_set_page_type_catalog_detail'];
    $set_page_type_values['set_page_type_express_order'] = $row['user_set_page_type_express_order'];
    $set_page_type_values['set_page_type_order_form'] = $row['user_set_page_type_order_form'];
    $set_page_type_values['set_page_type_shopping_cart'] = $row['user_set_page_type_shopping_cart'];
    $set_page_type_values['set_page_type_shipping_address_and_arrival'] = $row['user_set_page_type_shipping_address_and_arrival'];
    $set_page_type_values['set_page_type_shipping_method'] = $row['user_set_page_type_shipping_method'];
    $set_page_type_values['set_page_type_billing_information'] = $row['user_set_page_type_billing_information'];
    $set_page_type_values['set_page_type_order_preview'] = $row['user_set_page_type_order_preview'];
    $set_page_type_values['set_page_type_order_receipt'] = $row['user_set_page_type_order_receipt'];
    $contact_image = $row['contact_image'];
    $contact_file_id = $row['contact_file_id'];
    $contact_id = $row['contact_id'];
    // Contact columns come from a LEFT JOIN and are NULL for a user without a contact.
    $first_name = trim((string) $row['first_name']);
    $last_name = trim((string) $row['last_name']);
    $email_address = trim((string) $row['email_address']);
    $member_id = trim((string) $row['member_id']);

    $output_login_as_user_button = '';

    // If this user is not the editor, then output button to allow editor to login as user.
    // There is not an important reason why we don't allow a user to login as him/herself,
    // however we just decided to prevent this because there is really no reason to allow it and
    // it might cause confusing log messages eventually (e.g. "example_username -> example_username").
    // It might also cause confusion for the user about what happens if they do that.
    if ($id != USER_ID) {
        $output_login_as_user_button = '<a class="btn btn-sm btn-outline theme-secondary rounded-pill px-5" href="login_as_user.php?id=' . h($_GET['id']) . get_token_query_string_field() . '"><i class="bi bi-box-arrow-in-right me-1"></i>' . lang('Login as User') . '</a>';
    }

    // Resetting a password revokes every remembered session of that account
    // (reset_password.php -> pg_auth_token_revoke_user), so an editor who does
    // it to his/her own account is signed out on the next request - before the
    // screen carrying the new password gets to render it. The password then
    // only exists in the e-mail, and where mail is not configured it exists
    // nowhere at all, which is how an administrator locks the site's last
    // administrator out of it. The gate that counts is in reset_password.php;
    // here the button simply is not offered, and the change password screen -
    // which keeps the browser signed in - is offered in its place.
    $output_reset_password_button = '';
    $output_reset_password_form   = '';

    if ($id != USER_ID) {
        $output_reset_password_button = pg_user_form_reset_password_button();

        $output_reset_password_form =
            '<form name="reset_password_form" action="reset_password.php" method="post">'
            . get_token_field()
            . '<input type="hidden" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />'
            . '<input type="hidden" name="user_id" value="' . h($_GET['id']) . '">'
            . '</form>';

    } else {
        $output_reset_password_button = '<span class="fg-secondary small">' . h(lang('You cannot reset your own password here.')) . '</span>';

        // Linked only where the site actually has a change password page: a
        // button pointing at a page that is not there is worse than no button.
        $own_password_url = get_page_type_url('change password');

        if ($own_password_url) {
            $output_reset_password_button .= '<a class="btn btn-sm btn-outline theme-secondary rounded-pill px-5" href="' . h($own_password_url) . '"><i class="bi bi-key me-1"></i>' . lang('Change Password') . '</a>';
        }
    }

    // The last administrator may not be demoted or deleted: the site would be
    // left with nobody who can reach the admin-only screens.
    $allow_delete       = true;
    $allow_role_change  = true;
    $role_cards_disabled = true;

    if ($user['role'] <= 1) {

        $role_cards_disabled = false;

        if (($user['role'] == 0) && ($role == 0)) {

            $another_administrator = db_value("SELECT user_id FROM user
                WHERE (user_role = '0') AND (user_id != '" . escape($id) . "')");

            if ($another_administrator == '') {
                $allow_role_change   = false;
                $allow_delete        = false;
                $role_cards_disabled = true;
            }
        }
    }

    if ($allow_delete == true) {
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('user')))) . '"><i class="bi bi-trash me-3"></i><span class="pg-btn-label">' . lang(array('string'=>'Delete')) . '</span></button>';
    } else {
        $output_delete_button = '<button type="submit" name="submit_delete" class="btn my-1 btn-solid theme-danger disabled"><i class="bi bi-trash me-3"></i><span class="pg-btn-label">' . lang(array('string'=>'Delete')) . '</span></button>';
    }

    $output_password_hint_field = "";

    // Find out if password hint is enabled in the settings
    if (PASSWORD_HINT == TRUE) {
        $output_password_hint_field = 
            '<div class="col-12 xl:col-6 my-3">
                <label for="password_hint" class="form-label">' . lang('User Password Hint') . '</label>
                <input value="' . h($password_hint) . '" type="text" name="password_hint" id="password_hint" class="form-control" />
            </div>';
    }

    $badge_checked = '';
    $manage_visitors_checked = '';
    $create_pages_checked = '';
    $delete_pages_checked = '';
    $manage_forms_checked = '';
    $manage_calendars_checked = '';
    $manage_ecommerce_checked = '';
    $view_card_data_checked = '';
    $manage_ecommerce_reports_checked = '';
    $set_offline_payment_checked = '';
    $manage_contacts_checked = '';
    $manage_emails_checked = '';
    $publish_calendar_events_checked = '';
    
    // If badge is enabled, then check check box and show badge label row.
    if ($badge == 1) {
        $badge_checked = ' checked="checked"';
    }
    
    // find out if manage visitors checkbox should be checked or not
    if ($manage_visitors == 'yes') {
        $manage_visitors_checked = ' checked="checked"';
    }
    
    // find out if create pages checkbox should be checked or not
    if ($create_pages == '1') {
        $create_pages_checked = ' checked="checked"';
    }
    
    // find out if delete pages checkbox should be checked or not
    if ($delete_pages == '1') {
        $delete_pages_checked = ' checked="checked"';
    }
    
    // find out if manage forms checkbox should be checked or not
    if ($manage_forms == 'yes') {
        $manage_forms_checked = ' checked="checked"';
    }
    
    // find out if manage calendars checkbox should be checked or not
    if ($manage_calendars == 'yes') {
        $manage_calendars_checked = ' checked="checked"';
        $calendar_access_style = '';
        $publish_calendar_events_style = '';
        
    } else {
        $calendar_access_style = '; display: none';
        $publish_calendar_events_style = '; display: none';
    }
    
    // find out if publish calendar events should be checked or not
    if ($publish_calendar_events == 'yes') {
        $publish_calendar_events_checked = ' checked="checked"';
    }
    
    // if manage e-commerce is enabled, then check check box and show view card data check box
    if ($manage_ecommerce == 'yes') {
        $manage_ecommerce_checked = ' checked="checked"';
        $view_card_data_style = '';
        
    // else manage e-commerce is disabled, so uncheck check box and hide view card data check box
    } else {
        $view_card_data_style = '; display: none';
    }

    // find out if view card data checkbox should be checked or not
    if ($manage_ecommerce_reports) {
        $manage_ecommerce_reports_checked = ' checked="checked"';
    }

    if ($view_card_data == 1) {
        $view_card_data_checked = ' checked="checked"';
    }
    
    // find out if set offline payment checkbox should be checked or not
    if ($set_offline_payment == 1) {
        $set_offline_payment_checked = ' checked="checked"';
    }
    
    // find out if manage contacts checkbox should be checked or not
    if ($manage_contacts == 'yes') {
        $manage_contacts_checked = ' checked="checked"';
    }
    
    // find out if manage e-mails checkbox should be checked or not
    if ($manage_emails == 'yes') {
        $manage_emails_checked = ' checked="checked"';
    }
    
    $contact_group_access_style = '';
    
    // if manage contacts and manage emails is off, then hide list of contact groups
    if (($manage_contacts != 'yes') && ($manage_emails != 'yes')) {
        $contact_group_access_style = '; display: none';
    }
    
    // Folders this user may edit or view. Read before the panels are built
    // because both the folder trees and the row summaries need them.
    $folders_that_user_has_edit_access_to = array();

    $result = db_q("SELECT aclfolder_folder as folder_id FROM aclfolder
        WHERE (aclfolder_user = ?) AND (aclfolder_rights = '2')", array((string) $_GET['id'])) or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $folders_that_user_has_edit_access_to[] = $row['folder_id'];
    }

    $folders_that_user_has_view_access_to = array();

    $result = db_q("SELECT aclfolder_folder as folder_id FROM aclfolder
        WHERE (aclfolder_user = ?) AND (aclfolder_rights = '1')", array((string) $_GET['id'])) or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $folders_that_user_has_view_access_to[] = $row['folder_id'];
    }

    $common_regions_user_can_edit = get_items_user_can_edit('common_regions', $_GET['id']);
    $shared_components_user_can_edit = get_items_user_can_edit('shared_components', $_GET['id']);
    $menus_user_can_edit          = get_items_user_can_edit('menus', $_GET['id']);

    $output_hidden_role = '';

    // A manager may only manage users, so the role cards are read-only and the
    // role travels in a hidden field - a disabled control posts nothing.
    if ($user['role'] == 2) {
        $output_hidden_role = '<input type="hidden" name="role" value="3" />';
    }

    // Selection markup for the permission panels, built with this user's current
    // selections so the panels open on what they already have.
    $permission_panels = array(
        'edit_tree'      => get_acl_folder_tree('edit', 0, 0, array(), $folders_that_user_has_edit_access_to),
        'page_types'     => get_page_type_checkboxes_and_labels($set_page_type_values),
        'common_regions' => get_checkboxes_for_items_user_can_edit('common_regions', $common_regions_user_can_edit),
        'shared_components' => get_checkboxes_for_items_user_can_edit('shared_components', $shared_components_user_can_edit),
        'menus'          => get_checkboxes_for_items_user_can_edit('menus', $menus_user_can_edit),
        'view_tree'      => get_date_picker_format() . get_acl_folder_tree('view', 0, 0, array(), $folders_that_user_has_view_access_to, $_GET['id']),
    );

    if (FORMS == true) {
        $permission_panels['manage_forms_switch'] = pg_user_permission_switch(
            'manage_forms', 'yes', ($manage_forms == 'yes'), lang('Reach the data submitted through forms'));
    }

    // Calendars this user may post to, counted as the checkboxes are built.
    $calendar_count = 0;

    if (CALENDARS == true) {

        $output_calendars = '';

        $result = mysqli_query(db::$con, "SELECT id, name FROM calendars ORDER BY name") or output_error('Query failed.');

        while ($calendar = mysqli_fetch_assoc($result)) {

            $calendar_checked = db_value("SELECT user_id FROM users_calendars_xref
                WHERE (user_id = '" . escape($_GET['id']) . "') AND (calendar_id = '" . escape($calendar['id']) . "')");

            if ($calendar_checked != '') {
                $calendar_count++;
            }

            $output_calendars .= '<div class="form-field"><input type="checkbox" name="calendar_' . $calendar['id'] . '" id="calendar_' . $calendar['id'] . '" value="1" class="check multiselect-checkbox"' . (($calendar_checked != '') ? ' checked="checked"' : '') . ' /><label class="" for="calendar_' . $calendar['id'] . '"> ' . h($calendar['name']) . '</label></div>';
        }

        $permission_panels['calendars'] = $output_calendars;
    }

    if (ECOMMERCE == true) {

        $output_commerce_switches =
            pg_user_permission_switch('view_card_data', '1', ($view_card_data == 1), lang('View card data'))
            . pg_user_permission_switch('manage_ecommerce_reports', '1', ($manage_ecommerce_reports == 1), lang('Manage commerce reports'), lang('Order reports and the shipping report.'));

        if (ECOMMERCE_OFFLINE_PAYMENT == TRUE) {
            $output_commerce_switches .= pg_user_permission_switch('set_offline_payment', '1', ($set_offline_payment == 1), lang('Set the offline payment option on orders'));
        }

        $permission_panels['commerce_switches'] = $output_commerce_switches;
    }

    if (defined('ERP_ENABLED') && ERP_ENABLED) {

        // manage_erp is the gate and is rendered by the row itself; only the two
        // rights that sit behind it belong in the panel.
        $permission_panels['erp_switches'] =
            pg_user_permission_switch('manage_erp_cash', '1', ($manage_erp_cash == 1), lang('See cash and bank'), lang('Balances, receipts and payments.'))
            . pg_user_permission_switch('manage_erp_settings', '1', ($manage_erp_settings == 1), lang('Change ERP settings'), lang('Numbering, default accounts and the Parasut connection.'))
            . (pg_user_has_erp_readonly_column() ? pg_user_permission_switch('manage_erp_readonly', '1', ($manage_erp_readonly == 1), lang('Read only (accountant)'), lang('Every ERP screen to read and the accountant\'s pack to download; nothing can be issued, paid or changed.')) : '');
    }

    if (pg_user_permission_ws_available()) {
        $permission_panels['workspace_switches'] = pg_user_permission_ws_switches($ws_rights);
    }

    if (ADS === true) {
        $ad_regions_user_can_edit = get_items_user_can_edit('ad_regions', $_GET['id']);
        $permission_panels['ad_regions'] = get_checkboxes_for_items_user_can_edit('ad_regions', $ad_regions_user_can_edit);
    }

    // Contact groups. The checked state was computed here before and then left
    // out of the markup, so every group came up empty on this screen no matter
    // what the user actually had.
    $output_contact_groups = '';
    $contact_group_count   = 0;

    $result = mysqli_query(db::$con, "SELECT id, name FROM contact_groups ORDER BY name") or output_error('Query failed.');

    while ($contact_group = mysqli_fetch_assoc($result)) {

        $contact_group_checked = db_value("SELECT user_id FROM users_contact_groups_xref
            WHERE (user_id = '" . escape($_GET['id']) . "') AND (contact_group_id = '" . escape($contact_group['id']) . "')");

        if ($contact_group_checked != '') {
            $contact_group_count++;
        }

        $output_contact_groups .= '<div class="form-field"><input type="checkbox" name="contact_group_' . $contact_group['id'] . '" id="contact_group_' . $contact_group['id'] . '" value="1" class="check multiselect-checkbox"' . (($contact_group_checked != '') ? ' checked="checked"' : '') . ' /><label class="" for="contact_group_' . $contact_group['id'] . '">' . h($contact_group['name']) . '</label></div>';
    }

    $permission_panels['contact_groups'] = $output_contact_groups;

    // How many page types are set. The generator treats "no value at all" as
    // every type allowed, which is what a row saved before page types existed
    // looks like, so the same rule decides the count.
    $page_type_count = 0;

    foreach ($set_page_type_values as $set_page_type_value) {
        if (($set_page_type_value == 1) || ($set_page_type_value === '') || ($set_page_type_value === null)) {
            $page_type_count++;
        }
    }

    $permission_ui = pg_user_permission_ui(array(
        'values' => array(
            'create_pages'            => ($create_pages == 1) ? '1' : '',
            'delete_pages'            => ($delete_pages == 1) ? '1' : '',
            'manage_forms'            => $manage_forms,
            'manage_calendars'        => $manage_calendars,
            'publish_calendar_events' => $publish_calendar_events,
            'manage_visitors'         => $manage_visitors,
            'manage_contacts'         => $manage_contacts,
            'manage_emails'           => $manage_emails,
            'manage_ecommerce'        => $manage_ecommerce,
            // The two prefix-less gates store '1'. Without them here the rows
            // drew switched off and a save cleared the right.
            'manage_erp'              => ($manage_erp == 1) ? '1' : '',
            'manage_workspace'        => ($ws_rights['manage_workspace'] == 1) ? '1' : '',
        ),
        'panels' => $permission_panels,
        'counts' => array(
            'edit_folders'   => count($folders_that_user_has_edit_access_to),
            'view_folders'   => count($folders_that_user_has_view_access_to),
            'page_types'     => $page_type_count,
            'common_regions' => count($common_regions_user_can_edit),
            'shared_components' => count($shared_components_user_can_edit),
            'menus'          => count($menus_user_can_edit),
            'calendars'      => $calendar_count,
            'contact_groups' => $contact_group_count,
            'ad_regions'     => isset($ad_regions_user_can_edit) ? count($ad_regions_user_can_edit) : 0,
        ),
    ));

    $output_badge_label_info = '';
    $output_badge_label_placeholder = lang('Badge Label');

    // If there is a default badge label in the site settings,
    // then output info about how field can be left blank for default.
    if (BADGE_LABEL != '') {
        $output_badge_label_info = '<div class="form-text">(' . lang('leave blank for default') . ': "' . h(BADGE_LABEL) . '")</div>';
        $output_badge_label_placeholder = h(BADGE_LABEL);
    }

    // ── Identity header ──────────────────────────────────────────────────
    //
    // Who this account is, and the actions that act on the account rather than
    // on the form. They used to be a row of link buttons above the form with no
    // frame around them and no picture to anchor them.
    $identity_name = trim($first_name . ' ' . $last_name);

    if ($identity_name == '') {
        $identity_name = $username;
    }

    $identity_photo = '';

    if ($contact_id != '') {
        if ($contact_file_id != 0) {
            $identity_photo = PATH . db_value("SELECT name FROM files WHERE id = '" . escape($contact_file_id) . "'");
        } elseif ($contact_image != '') {
            $identity_photo = $contact_image;
        }
    }

    if ($identity_photo != '') {
        $output_identity_picture = '<img class="pg-identity-pic" src="' . h($identity_photo) . '" alt="" />';
    } else {
        $output_identity_picture = '<span class="pg-identity-pic">' . h(mb_strtoupper(mb_substr($identity_name, 0, 1, 'UTF-8'), 'UTF-8')) . '</span>';
    }

    // ── Sessions ─────────────────────────────────────────────────────────
    //
    // Rendered as rows rather than a table: six columns wrap the device name
    // onto three lines inside a panel this wide.
    $edit_user_sessions_back  = 'edit_user.php?id=' . (int) $_GET['id']
        . (isset($_REQUEST['send_to']) ? '&send_to=' . urlencode($_REQUEST['send_to']) : '');
    $edit_user_sessions_count = 0;
    $edit_user_session_rows   = '';
    $edit_user_session_latest = 0;

    $edit_user_current_selector = '';

    if (isset($_COOKIE['software']['auth'])) {
        $edit_user_cookie_parts = explode(':', (string) $_COOKIE['software']['auth'], 2);
        $edit_user_current_selector = (string) $edit_user_cookie_parts[0];
    }

    $edit_user_pin_column = pg_schema_has('auth_tokens', 'pinned');

    $edit_user_sessions = db_items(
        "SELECT selector, user_agent, ip_address, created_at, last_used_at, expires_at"
        . ($edit_user_pin_column ? ", pinned" : "") . "
        FROM auth_tokens
        WHERE user_id = '" . escape($_GET['id']) . "' AND expires_at > '" . time() . "'
        ORDER BY GREATEST(last_used_at, created_at) DESC");

    if ($edit_user_sessions) {

        foreach ($edit_user_sessions as $edit_user_session) {

            $edit_user_sessions_count++;

            $edit_user_session_fresh = max((int) $edit_user_session['last_used_at'], (int) $edit_user_session['created_at']);

            if ($edit_user_session_fresh > $edit_user_session_latest) {
                $edit_user_session_latest = $edit_user_session_fresh;
            }

            $edit_user_session_tags = '';

            if (($edit_user_current_selector !== '') && hash_equals((string) $edit_user_session['selector'], $edit_user_current_selector)) {
                $edit_user_session_tags .= '<span class="badge theme-success fw-light">' . h(lang('This device')) . '</span>';
            }

            if ((time() - $edit_user_session_fresh) < 300) {
                $edit_user_session_tags .= '<span class="badge theme-success fw-light">' . h(lang('Online')) . '</span>';
            }

            $edit_user_session_pinned = ($edit_user_pin_column && isset($edit_user_session['pinned']) && (((int) $edit_user_session['pinned']) === 1));

            if ($edit_user_session_pinned) {
                $edit_user_session_tags .= '<span class="badge theme-secondary fw-light" title="' . h(lang('Locked by the site owner: the device limit cannot evict it and the member cannot sign it out.')) . '"><i class="bi bi-pin-angle-fill"></i> ' . h(lang('Locked')) . '</span>';
            }

            $edit_user_session_pin_button = '';

            if ($edit_user_pin_column) {
                $edit_user_session_pin_button =
                    '<form method="post" action="view_sessions.php" style="margin:0">' . get_token_field()
                    . '<input type="hidden" name="pg_session_action" value="' . ($edit_user_session_pinned ? 'unpin' : 'pin') . '"/>'
                    . '<input type="hidden" name="pg_selector" value="' . h((string) $edit_user_session['selector']) . '"/>'
                    . '<input type="hidden" name="send_back" value="' . h($edit_user_sessions_back) . '"/>'
                    . '<button type="submit" class="btn btn-sm btn-text theme-secondary" title="' . h($edit_user_session_pinned ? lang('Unlock') : lang('Lock')) . '"><i class="bi ' . ($edit_user_session_pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle') . '"></i></button>'
                    . '</form>';
            }

            $edit_user_session_rows .=
                '<div class="pg-session">'
                . '<i class="bi ' . (preg_match('/(iphone|android|mobile|ipad)/i', (string) $edit_user_session['user_agent']) ? 'bi-phone' : 'bi-laptop') . '"></i>'
                . '<div class="pg-session-text">'
                . '<div class="pg-session-name">' . h(pg_user_agent_label($edit_user_session['user_agent'])) . $edit_user_session_tags . '</div>'
                . '<div class="pg-session-meta">' . h((string) $edit_user_session['ip_address'])
                . ' &middot; ' . h(lang(array('string' => 'Opened {var:1}', 'vars' => get_absolute_time(array('timestamp' => (int) $edit_user_session['created_at'])))))
                . ' &middot; ' . h(lang(array('string' => 'Expires {var:1}', 'vars' => get_absolute_time(array('timestamp' => (int) $edit_user_session['expires_at'])))))
                . '</div></div>'
                . '<div class="pg-session-actions">' . $edit_user_session_pin_button
                . '<form method="post" action="view_sessions.php" style="margin:0">' . get_token_field()
                . '<input type="hidden" name="pg_session_action" value="revoke"/>'
                . '<input type="hidden" name="pg_selector" value="' . h((string) $edit_user_session['selector']) . '"/>'
                . '<input type="hidden" name="send_back" value="' . h($edit_user_sessions_back) . '"/>'
                . '<button type="submit" class="btn btn-sm btn-outline theme-danger">' . h(lang('Sign out')) . '</button>'
                . '</form></div></div>';
        }
    }

    // ── Sign-in lockout ──────────────────────────────────────────────────
    $output_sign_in_lock_notice = '';
    $output_sign_in_unlock_form = '';
    $output_identity_lock_tag   = '';

    $sign_in_lock = pg_login_lock_state($username, $email);

    if ($sign_in_lock['locked']) {

        $sign_in_lock_minutes = max(1, (int) ceil(($sign_in_lock['until'] - time()) / 60));

        $output_identity_lock_tag = '<span class="badge theme-danger fw-light"><i class="bi bi-lock-fill me-1"></i>' . h(lang('Sign-in Locked')) . '</span>';

        $output_sign_in_lock_notice =
            '<div class="alert theme-danger d-flex align-items-center gap-3 py-3">'
            . '<i class="bi bi-lock-fill"></i>'
            . '<span><b>' . h(lang('Sign-in is locked.')) . '</b> ' . h(lang(array(
                'string' => 'Too many failed attempts. Clears on its own in {var:1} minutes.',
                'vars'   => $sign_in_lock_minutes))) . '</span>'
            . '<a class="btn btn-sm btn-outline theme-danger rounded-pill px-5 ms-auto" href="#" onclick="event.preventDefault(); document.unlock_sign_in_form.submit(); return false;"><i class="bi bi-unlock me-1"></i>' . h(lang('Unlock Sign-in')) . '</a>'
            . '</div>';

        $output_sign_in_unlock_form =
            '<form name="unlock_sign_in_form" action="edit_user.php" method="post">'
            . get_token_field()
            . '<input type="hidden" name="unlock_sign_in" value="1">'
            . '<input type="hidden" name="id" value="' . h($_GET['id']) . '">'
            . '<input type="hidden" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />'
            . '</form>';
    }

    // ── Identity tags ────────────────────────────────────────────────────
    $output_identity_tags =
        '<span class="badge theme-primary fw-light">' . h(pg_user_role_name($role)) . '</span>'
        . implode('', array_map(function ($identity_provider) {
            $identity_definition = pg_connect_provider($identity_provider);
            $identity_label = pg_signin_provider_label($identity_provider);
            return '<span class="badge badge-subtle theme-secondary border fw-light" title="' . h(lang(array('string' => 'This account can sign in with {var:1}.', 'vars' => $identity_label))) . '">'
                . '<i class="bi ' . h(is_array($identity_definition) ? $identity_definition['icon'] : 'bi-person-badge') . ' me-1"></i>' . h($identity_label) . '</span>';
        }, array_keys($user_identities)))
        . ((($edit_user_session_latest > 0) && ((time() - $edit_user_session_latest) < 300)) ? '<span class="badge theme-success fw-light">' . h(lang('Online')) . '</span>' : '')
        . $output_identity_lock_tag;

    // ── Contact card ─────────────────────────────────────────────────────
    if ($contact_id != '') {

        $output_contact_lines = '<span>' . h($email_address) . '</span>';

        if ($member_id != '') {
            $output_contact_lines .= '<span>' . h(MEMBER_ID_LABEL) . ': ' . h($member_id) . '</span>';
        }

        $output_contact_body =
            '<a class="pg-contact-card pg-link-body-emphasis text-decoration-none" href="edit_contact.php?id=' . (int) $contact_id . '&send_to=' . h(urlencode(get_request_uri())) . '">'
            . (($identity_photo != '')
                ? '<img class="pg-contact-pic" src="' . h($identity_photo) . '" alt="" />'
                : '<span class="pg-contact-pic d-inline-flex align-items-center justify-content-center bg-subtle-secondary fg-secondary fs-xl"><i class="bi bi-person"></i></span>')
            . '<span class="pg-contact-who"><b>' . h($identity_name) . '</b>' . $output_contact_lines . '</span>'
            . '<i class="bi bi-chevron-right ms-auto fg-secondary"></i>'
            . '</a>';

        $output_contact_header =
            '<a class="btn btn-sm btn-text theme-secondary" href="edit_contact.php?id=' . (int) $contact_id . '&send_to=' . h(urlencode(get_request_uri())) . '">' . h(lang('Open contact')) . '</a>';

        // The ledger account behind the contact, for an operator the ERP lets
        // in: user -> contact -> account is one chain, and the last link is
        // worth a click from here. Read from the account side, the truth;
        // contacts.erp_account_id is only a mirror.
        if (defined('ERP_ENABLED') && ERP_ENABLED && (($user['role'] < 3) || !empty($user['manage_erp']))) {
            $erp_account = db_item("SELECT id, balance FROM erp_accounts WHERE contact_id = '" . (int) $contact_id . "' ORDER BY id ASC LIMIT 1");

            if (is_array($erp_account)) {
                require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

                $erp_balance = (int) $erp_account['balance'];
                $erp_side = ($erp_balance > 0) ? lang('owes you') : (($erp_balance < 0) ? lang('you owe') : '');

                $output_contact_header .=
                    '<a class="btn btn-sm btn-text theme-secondary" href="edit_erp_account.php?id=' . (int) $erp_account['id'] . '"><i class="bi bi-journal-text me-1"></i>' . h(lang('Ledger account')) . ': <b class="' . (($erp_balance > 0) ? 'fg-success' : (($erp_balance < 0) ? 'fg-danger' : '')) . '">' . h(erp_money_out(abs($erp_balance))) . '</b>' . (($erp_side !== '') ? ' <span class="fg-secondary">' . h($erp_side) . '</span>' : '') . '</a>';
            } else {
                $output_contact_header .=
                    '<a class="btn btn-sm btn-text theme-secondary" href="add_erp_account.php?contact_id=' . (int) $contact_id . '"><i class="bi bi-journal-plus me-1"></i>' . h(lang('Open an Account')) . '</a>';
            }
        }

    } else {

        $output_contact_body =
            '<p class="small fg-secondary mb-3">' . h(lang('This User does not currently have a Contact connected to it. Connecting a Contact to this User is required to use the Membership features. A Contact will be created and connected to this User automatically when the User performs certain actions (e.g. updates his/her profile, submits a Custom Form, submits an Order, and etc.). However, if for any reason, you would like to do this now, you can.')) . '</p>'
            . '<button type="submit" id="submit_create_contact" name="submit_create_contact" value="Create Contact" class="btn btn-sm btn-outline theme-secondary rounded-pill px-5"><i class="bi bi-person-plus me-1"></i><span class="pg-btn-label">' . h(lang('Create Contact')) . '</span></button>';

        $output_contact_header = '';
    }

    $output_contact_card =
        '<div class="card mb-5">
            <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Contact')) . '</span>
                <span class="d-flex flex-wrap justify-content-end gap-1">' . $output_contact_header . '</span>
            </div>
            <div class="card-body">' . $output_contact_body . '</div>
        </div>';

    // ── Sign-in and sessions card ────────────────────────────────────────
    //
    // The password date needs the column the 2026.4.4 step adds; a row that
    // predates it carries 0, which is honestly "not known" rather than a date
    // guessed from the log.
    $password_changed_at = pg_user_has_password_changed_at()
        ? (int) db_value("SELECT user_password_changed_at FROM user WHERE user_id = '" . escape($_GET['id']) . "'")
        : 0;

    $output_password_note = ($password_changed_at > 0)
        ? lang(array('string' => 'Last changed {var:1}', 'vars' => get_absolute_time(array('timestamp' => $password_changed_at, 'type' => 'date'))))
        : lang('Change date not recorded');

    $output_identity_rows = $user_identities
        ? ''
        : '<div class="pg-fact">
                <span class="pg-fact-icon"><i class="bi bi-key"></i></span>
                <div class="pg-fact-text"><b>' . h(lang('Signs in with a password only')) . '</b><span>' . h(lang('No Google, Microsoft or Apple account is connected.')) . '</span></div>
            </div>';

    foreach ($user_identities as $identity_provider => $identity) {
        $identity_definition = pg_connect_provider($identity_provider);
        $output_identity_rows .= '<div class="pg-fact">
                <span class="pg-fact-icon"><i class="bi ' . h(is_array($identity_definition) ? $identity_definition['icon'] : 'bi-person-badge') . '"></i></span>
                <div class="pg-fact-text"><b>' . h(lang(array('string' => 'Sign in with {var:1}', 'vars' => pg_signin_provider_label($identity_provider)))) . '</b><span>' . h(lang('Connected'))
                    . (((string) $identity['email'] !== '') ? ' &middot; ' . h($identity['email']) : '') . '</span></div>
            </div>';
    }

    // One's own account: a provider that is on and not connected yet can be
    // connected from here, whatever its e-mail address (signin.php,
    // &connect=). Not for another person's account: the provider account
    // would be the editor's.
    if ((int) $id === (int) USER_ID) {
        $output_connect_buttons = pg_signin_connect_buttons((int) $id, get_request_uri(), true);

        if ($output_connect_buttons !== '') {
            $output_identity_rows .= '<div class="pg-fact">
                <span class="pg-fact-icon"><i class="bi bi-link-45deg"></i></span>
                <div class="pg-fact-text"><b>' . h(lang('Connect a sign-in')) . '</b><span>' . h(lang('Connect your own account at a provider to sign in with it too.')) . '</span>'
                    . $output_connect_buttons . '</div>
            </div>';
        }
    }

    $output_sessions_row = ($edit_user_sessions_count > 0)
        ? '<div class="pg-fact">
                <span class="pg-fact-icon"><i class="bi bi-laptop"></i></span>
                <div class="pg-fact-text"><b>' . h(lang(array('string' => '{var:1} active session{suffix:1}', 'vars' => $edit_user_sessions_count, 'suffix' => (($edit_user_sessions_count == 1) ? '' : 's')))) . '</b><span>' . h(lang('Last used')) . ' ' . get_relative_time(array('timestamp' => $edit_user_session_latest)) . '</span></div>
                <button type="button" class="btn btn-sm btn-text theme-secondary pg-fact-action" data-bs-toggle="drawer" data-bs-target="#pg_sessions_panel">' . h(lang('Manage')) . '<i class="bi bi-chevron-right ms-1"></i></button>
            </div>'
        : '<div class="pg-fact">
                <span class="pg-fact-icon"><i class="bi bi-laptop"></i></span>
                <div class="pg-fact-text"><b>' . h(lang('No active sessions')) . '</b></div>
                ' . ($user_identities ? '<button type="button" class="btn btn-sm btn-text theme-secondary pg-fact-action" data-bs-toggle="drawer" data-bs-target="#pg_sessions_panel">' . h(lang('Manage')) . '<i class="bi bi-chevron-right ms-1"></i></button>' : '') . '
            </div>';

    // Two-step verification: a fact row, and turning it off in the side
    // panel. Absent until the 8.40 upgrade has created the tables. There is
    // no way to turn it on here: the key is confirmed on the person's own
    // device, from their account page.
    $edit_user_mfa_on = pg_mfa_table_exists() && pg_mfa_has_factor((int) $_GET['id']);
    $edit_user_mfa_self = ((int) $_GET['id'] === (int) USER_ID);

    $output_mfa_state = h($edit_user_mfa_on ? lang('On') : lang('Off'));

    if (!$edit_user_mfa_on && $edit_user_mfa_self && pg_mfa_table_exists() && pg_mfa_available()) {
        $edit_user_mfa_account_url = pg_mfa_account_url();

        $output_mfa_state .= ' &middot; ' . (($edit_user_mfa_account_url !== '')
            ? '<a href="' . h($edit_user_mfa_account_url) . '">' . h(lang('Turn it on from your account page.')) . '</a>'
            : h(lang('Turn it on from your account page.')));
    }

    $output_mfa_row = pg_mfa_table_exists()
        ? '<div class="pg-fact">
                <span class="pg-fact-icon"><i class="bi bi-shield-lock"></i></span>
                <div class="pg-fact-text"><b>' . h(lang('Two-step verification')) . '</b><span>' . $output_mfa_state . '</span></div>
                ' . ($edit_user_mfa_on ? '<button type="button" class="btn btn-sm btn-text theme-secondary pg-fact-action" data-bs-toggle="drawer" data-bs-target="#pg_sessions_panel">' . h(lang('Manage')) . '<i class="bi bi-chevron-right ms-1"></i></button>' : '') . '
            </div>'
        : '';

    $output_sign_in_card =
        '<div class="card mb-5">
            <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Sign-in and Sessions')) . '</span>
            </div>
            <div class="card-body">
                <div class="pg-fact">
                    <span class="pg-fact-icon"><i class="bi bi-key"></i></span>
                    <div class="pg-fact-text"><b>' . h(lang('Password')) . '</b><span>' . h($output_password_note) . '</span></div>
                </div>
                ' . $output_identity_rows . $output_mfa_row . $output_sessions_row . '
            </div>
        </div>';

    // ── Recent activity ──────────────────────────────────────────────────
    $log_all   = db_items("SELECT log_description, log_ip, log_timestamp FROM log
        WHERE log_user = '" . escape($username) . "' ORDER BY log_id DESC LIMIT 100");

    $log_recent_rows = '';
    $log_panel_rows  = '';
    $log_count       = 0;

    if ($log_all) {

        foreach ($log_all as $log_row) {

            $log_count++;

            $log_line =
                '<div class="pg-log-line">'
                . '<span class="pg-log-when">' . get_relative_time(array('timestamp' => $log_row['log_timestamp'])) . '</span>'
                . '<span class="pg-log-what">' . convert_text_to_html($log_row['log_description']) . '</span>'
                . '<span class="pg-log-ip">' . h($log_row['log_ip']) . '</span>'
                . '</div>';

            if ($log_count <= 5) {
                $log_recent_rows .= $log_line;
            }

            $log_panel_rows .= $log_line;
        }
    }

    $output_activity_card =
        '<div class="card mb-5">
            <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Recent Activity')) . '</span>
                ' . (($log_count > 5) ? '<button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="drawer" data-bs-target="#pg_activity_panel">' . h(lang(array('string' => 'All ({var:1})', 'vars' => $log_count))) . '</button>' : '') . '
            </div>
            <div class="card-body">
                ' . (($log_count > 0) ? $log_recent_rows : '<p class="small fg-secondary mb-0">' . h(lang('No activity recorded yet.')) . '</p>') . '
            </div>
        </div>';

    // ── The panels that live outside the form ────────────────────────────
    //
    // Sessions and the sign-in connections are their own forms, and forms
    // cannot nest, so these sit after the main form closes. The panel says as
    // much: the buttons in it act at once rather than on Save.
    $output_identity_panel = '';

    foreach ($user_identities as $identity_provider => $identity) {
        $identity_definition = pg_connect_provider($identity_provider);
        $identity_label = pg_signin_provider_label($identity_provider);
        $output_identity_panel .= '<div class="pg-perm-block">
                <div class="pg-perm-block-title">' . h(lang(array('string' => 'Sign in with {var:1}', 'vars' => $identity_label))) . '</div>
                <div class="d-flex align-items-start gap-5 border rounded-4 p-5">
                    <span class="pg-fact-icon"><i class="bi ' . h(is_array($identity_definition) ? $identity_definition['icon'] : 'bi-person-badge') . '"></i></span>
                    <div class="flex-grow-1">
                        <p class="small mb-0">' . h(lang(array('string' => 'The connection is tied to the {var:1} account itself, not to the email address - changing the email here does not remove it.', 'vars' => $identity_label))) . '</p>
                    </div>
                    <form method="post" action="edit_user.php" style="margin:0">' . get_token_field()
                        . '<input type="hidden" name="pg_unlink_identity" value="' . h($identity_provider) . '"/>'
                        . '<input type="hidden" name="id" value="' . h($_GET['id']) . '"/>'
                        . '<input type="hidden" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '"/>'
                        . '<button type="submit" class="btn btn-sm btn-outline theme-danger">' . h(lang(array('string' => 'Disconnect {var:1}', 'vars' => $identity_label))) . '</button>'
                    . '</form>
                </div>
            </div>';
    }

    // Turned off at once, one's own account included; see the handler above.
    // Not offered on one's own account while signed in as another user.
    $output_mfa_panel = '';

    if ($edit_user_mfa_on && $edit_user_mfa_self && !empty($_SESSION['software']['logged_in_as_different_user'])) {
        $output_mfa_panel =
            '<div class="pg-perm-block">
                <div class="pg-perm-block-title">' . h(lang('Two-step verification')) . '</div>
                <p class="small fg-secondary mb-0">' . h(lang('Two-step verification cannot be changed while signed in as another user.')) . '</p>
            </div>';

    } elseif ($edit_user_mfa_on) {
        $output_mfa_note = $edit_user_mfa_self
            ? lang('Turning it off also signs out your other devices.')
                . (pg_mfa_required_for_user((int) USER_ID) ? ' ' . lang('Your role requires two-step verification; after you turn it off you will be asked to set it up again at your next sign-in.') : '')
            : lang('Turns off the person\'s second step and signs them out everywhere. Use it when they lost their authenticator app and recovery codes; they sign in with the password alone afterwards and can set it up again.');

        $output_mfa_panel =
            '<div class="pg-perm-block">
                <div class="pg-perm-block-title">' . h(lang('Two-step verification')) . '</div>
                <div class="d-flex align-items-start gap-5 border rounded-4 p-5">
                    <span class="pg-fact-icon"><i class="bi bi-shield-lock"></i></span>
                    <div class="flex-grow-1">
                        <p class="small mb-0">' . h($output_mfa_note) . '</p>
                    </div>
                    <form method="post" action="edit_user.php" class="disable_shortcut" style="margin:0">' . get_token_field()
                        . '<input type="hidden" name="pg_mfa_reset" value="1"/>'
                        . '<input type="hidden" name="id" value="' . h($_GET['id']) . '"/>'
                        . '<input type="hidden" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '"/>'
                        . '<button type="submit" class="btn btn-sm btn-outline theme-danger">' . h(lang('Turn off two-step verification')) . '</button>'
                    . '</form>
                </div>
            </div>';
    }

    $output_sessions_list = ($edit_user_sessions_count > 0)
        ? '<div class="pg-perm-block">
                <div class="pg-perm-block-title">' . h(lang(array('string' => '{var:1} active session{suffix:1}', 'vars' => $edit_user_sessions_count, 'suffix' => (($edit_user_sessions_count == 1) ? '' : 's')))) . '</div>
                <div class="card"><div class="card-body p-0">' . $edit_user_session_rows . '</div></div>
                <form method="post" action="view_sessions.php" class="mt-3">' . get_token_field()
                    . '<input type="hidden" name="pg_session_action" value="revoke_user"/>'
                    . '<input type="hidden" name="pg_user_id" value="' . (int) $_GET['id'] . '"/>'
                    . '<input type="hidden" name="send_back" value="' . h($edit_user_sessions_back) . '"/>'
                    . '<button type="submit" class="btn btn-sm btn-outline theme-danger"><i class="bi bi-box-arrow-right me-1"></i>' . h(lang('Sign out all sessions')) . '</button>'
                . '</form>
            </div>'
        : '<p class="small fg-secondary">' . h(lang('No active sessions.')) . '</p>';

    $output_sessions_panel =
        '<dialog class="drawer drawer-end pg-perm-panel" id="pg_sessions_panel" aria-labelledby="pg_sessions_panel_title">
            <div class="drawer-header">
                <span class="pg-perm-icon" style="--pg-perm-color:#64b5f6"><i class="bi bi-shield-lock"></i></span>
                <h5 class="drawer-title ms-3" id="pg_sessions_panel_title">' . h(lang('Sign-in and Sessions')) . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body">
                <p class="pg-perm-panel-lead">' . h(lang('These act at once and do not wait for Save.')) . '</p>
                ' . $output_sessions_list . $output_identity_panel . $output_mfa_panel . '
            </div>
            <div class="drawer-footer">
                <div class="pg-perm-panel-count">' . h(lang('Sessions expire on their own after 30 days.')) . '</div>
                <button type="button" class="btn btn-sm btn-solid theme-primary rounded-pill px-5" data-bs-dismiss="drawer">' . h(lang('Done')) . '</button>
            </div>
        </dialog>'
        . (($log_count > 5)
            ? '<dialog class="drawer drawer-end pg-perm-panel" id="pg_activity_panel" aria-labelledby="pg_activity_panel_title">
                <div class="drawer-header">
                    <span class="pg-perm-icon" style="--pg-perm-color:#9aa3ab"><i class="bi bi-clock-history"></i></span>
                    <h5 class="drawer-title ms-3" id="pg_activity_panel_title">' . h(lang('Recent Activity')) . '</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
                </div>
                <div class="drawer-body">' . $log_panel_rows . '</div>
            </dialog>'
            : '');

    // Where it was talked about in the workspace, and the tasks about it.
    $output_workspace_button = '';

    if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
        $output_workspace_button = ws_record_button($user, 'user_account', (int) $_GET['id'], (string) $username, 'btn btn-sm btn-outline theme-secondary rounded-pill px-5');
    }

    print
    pg_page_shell(
        array(
            'title'=> lang('Edit User') . ' : ' . h($username),
            'extra classes'=>'users',
            'icon'=>'account',
            'heading'=>lang('Edit User'),
            'heading_description' => lang('Update this user\'s privileges, or email a new password.'),
            'cancel' => array('enable' => 'true', 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_users.php')),
            'breadcrumb' => array(
                array('label' => lang('Users'), 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_users.php')),
                array('label' => $username),
            ),
        )
    )  . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                <div class="card mb-5">
                    <div class="card-body py-5">
                        <div class="pg-identity">
                            ' . $output_identity_picture . '
                            <div class="pg-identity-who">
                                <div class="pg-identity-name">' . h($identity_name) . $output_identity_tags . '</div>
                                <div class="pg-identity-meta">' . h($username) . ' &middot; ' . h($email) . '</div>
                            </div>
                            <div class="pg-identity-actions">
                                ' . $output_login_as_user_button . '
                                ' . $output_reset_password_button . '
                                ' . $output_workspace_button . '
                            </div>
                        </div>
                    </div>
                </div>
                ' . $output_sign_in_lock_notice . '
                ' . $output_sign_in_unlock_form . '
                ' . $output_reset_password_form . '
                <form name="form" action="edit_user.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                    ' . $output_hidden_role . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <input type="hidden" name="current_url" value="' . h(get_request_uri()) . '" />
                    <div class="row g-5">
                        <div class="col-12 xl:col-8">

                            <div class="card mb-5">
                                <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                                    <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . lang('Role') . '</span>
                                    <span class="small fg-secondary">' . lang('Changing the role changes the rights below.') . '</span>
                                </div>
                                <div class="card-body">
                                    ' . pg_user_role_cards($role, $user['role'], $role_cards_disabled) . '
                                </div>
                            </div>

                            <div class="card mb-5">
                                <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                                    <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . lang('Account') . '</span>
                                </div>
                                <div class="card-body">
                                    <div class="row g-5">
                                        <div class="col-12 lg:col-4">
                                            <label for="username" class="form-label">' . lang('Username') . '</label>
                                            <input value="' . h($username) . '" type="text" name="username" placeholder="' . lang('Username') . '" id="username" maxlength="100" class="form-control" required />
                                            <div class="form-text">' . lang('Used when signing in.') . '</div>
                                        </div>
                                        <div class="col-12 lg:col-4">
                                            <label class="form-label" for="email">' . lang('User Email') . '</label>
                                            <input value="' . h($email) . '" type="email" class="form-control" id="email" name="email" maxlength="100" inputmode="email" data-inputmask-alias="email" required/>
                                            <div class="form-text">' . lang('Password reset goes to this address.') . '</div>
                                        </div>
                                        <div class="col-12 lg:col-4">
                                            <label for="home_page" class="form-label">' . lang('User Start Page') . '</label>
                                            <select class="form-control" name="home_page" id="home_page"><option value="0">[' . lang('None') . ']</option>' . select_page($home) . '</select>
                                            <div class="form-text">' . lang('Opened after signing in.') . '</div>
                                        </div>
                                    </div>

                                    <hr class="border-subtle-secondary opacity-50 my-5">
                                    <div class="text-uppercase small fw-semibold fg-secondary mb-3">' . lang('Optional') . '</div>

                                    <div class="row g-5">
                                        ' . $output_password_hint_field . '
                                        <div class="col-12 sm:col-6 lg:col-4">
                                            <label class="form-label" for="reward_points">' . lang('Reward Program, Reward Points') . '</label>
                                            <input value="' . h($reward_points) . '" type="number" class="form-control" id="reward_points" name="reward_points" maxlength="9"/>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input type="checkbox"' . $badge_checked . ' id="badge" name="badge" value="1" class="collapse-switcher" data-bs-target="#badge_row"/></div>
                                                <label class="" for="badge">' . lang('Show badge next to username') . '</label>
                                            </div>
                                            <div class="collapse ms-7 mt-1 mb-3' . (($badge_checked != '') ? ' show' : '') . '" id="badge_row" style="max-width:22rem">
                                                <input value="' . h($badge_label) . '" type="text" name="badge_label" placeholder="' . $output_badge_label_placeholder . '" id="badge_label" aria-label="' . h(lang('Badge Label')) . '" class="form-control form-control-sm" size="20" maxlength="100" />
                                                ' . $output_badge_label_info . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            ' . pg_user_form_rights_cards($permission_ui['rows']) . '
                        </div>

                        <div class="col-12 xl:col-4">
                            ' . $output_contact_card . '
                            ' . $output_sign_in_card . '
                            ' . $output_activity_card . '
                        </div>
                    </div>

                    ' . $permission_ui['panels'] . '

                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="bi bi-floppy me-3"></span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
                                ' . $output_delete_button . '
                            </div>
                        </div>
                    </nav>
                </form>
                ' . $output_sessions_panel . '
            </div>
        </div>
    
</main>' .
    output_footer();
        
} else {
    validate_token_field();

    // The account this request changes, as the audit trail names it.
    $audit_user_id = (isset($_POST['id']) && is_scalar($_POST['id'])) ? (int) $_POST['id'] : 0;
    
    // if create contact was selected, then create contact
    if (($_POST['submit_create_contact'] ?? '') == 'Create Contact') {
        // get e-mail address for this user
        $query =
            "SELECT user_email
            FROM user
            WHERE user_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $email_address = $row['user_email'];
        
        $query =
            "INSERT INTO contacts (
                email_address,
                user,
                timestamp)
            VALUES (
                ?,
                ?,
                UNIX_TIMESTAMP())";
        $result = db_q($query, array((string) $email_address, (string) $user['id'])) or output_error('Query failed.');
        
        $contact_id = mysqli_insert_id(db::$con);

        // A user account that had no contact record now has one.
        pg_announce_contact_created($contact_id);
        
        $audit_before = pg_audit_fetch('user', $audit_user_id);

        // connect contact to user
        $query =
            "UPDATE user
            SET
                user_contact = ?,
                user_user = ?,
                user_timestamp = UNIX_TIMESTAMP()
            WHERE user_id = ?";
        $result = db_q($query, array((string) $contact_id, (string) $user['id'], (string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        pg_audit_record('update', 'user', $audit_user_id, $audit_before);
        
        // forward user to contact that was just created
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id . '&send_to=' . urlencode($_POST['current_url']));
        exit();
    
    // else if user was selected for delete
    } else if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // get role for user that is being deleted
        $query =
            "SELECT user_role
            FROM user
            WHERE user_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $role = $row['user_role'];
        
        // assume that user is allowed to be deleted until we find out otherwise
        $allow_delete = true;
        
        // if the editor is an administrator and the user that is being edited is an administrator, find out if user is allowed to be deleted
        if (($user['role'] == 0) && ($role == 0)) {
            // check to see if there is another administrator user, other than this user that is being edited
            $query =
                "SELECT user_id
                FROM user
                WHERE
                    (user_role = '0')
                    AND (user_id != ?)";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // if there is no other administrator user, do not allow user to be deleted
            if (mysqli_num_rows($result) == 0) {
                $allow_delete = false;
            }
        }

        // if the user is allowed to be deleted
        if ($allow_delete == true) {
            // The account as it was, before anything that goes with it is
            // removed.
            $audit_before = pg_audit_fetch('user', $audit_user_id);

            // Revoke sign-in tokens first, as delete_users.php does, so no token
            // outlives the user row it belongs to.
            pg_auth_token_revoke_user((int) ($_POST['id'] ?? 0));

            // The second factor, its recovery codes and the account's
            // passkeys go with the account, as in delete_users.php: MySQL 5.7
            // hands the highest id out again after a restart, and a new
            // account would inherit them.
            pg_mfa_reset((int) ($_POST['id'] ?? 0));

            // The Microsoft and Apple identities go with the account.
            pg_signin_identities_forget((int) ($_POST['id'] ?? 0));

            // So do its favourites and back-in-stock requests.
            pg_wishlist_forget_user((int) ($_POST['id'] ?? 0));
            pg_stock_notify_forget_user((int) ($_POST['id'] ?? 0));

            // delete user record
            $query = "DELETE FROM user WHERE user_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

            pg_audit_record('delete', 'user', $audit_user_id, $audit_before);
            
            // delete aclfolder records
            $query = "DELETE FROM aclfolder WHERE aclfolder_user = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete users_contact_groups_xref records
            $query = "DELETE FROM users_contact_groups_xref WHERE user_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete users_calendars_xref records
            $query = "DELETE FROM users_calendars_xref WHERE user_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete address book records
            $query = "DELETE FROM address_book WHERE user = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete ad region xref records
            $query = "DELETE FROM users_ad_regions_xref WHERE user_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete common region xref records
            $query = "DELETE FROM users_common_regions_xref WHERE user_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete menu xref records
            $query = "DELETE FROM users_menus_xref WHERE user_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

            db("DELETE FROM users_messages_xref WHERE user_id = '" . e($_POST['id'] ?? '') . "'");

            // delete shared component xref records
            if (pg_schema_has('users_shared_components_xref')) db("DELETE FROM users_shared_components_xref WHERE user_id = '" . e($_POST['id'] ?? '') . "'");
            
            log_activity(lang(array('string'=>'user ({var:1}) was deleted','vars'=>$_POST['username'])), $_SESSION['sessionusername']);
            
            $liveform_view_users->add_notice(lang('The user has been deleted.'));
            
            // If there is a send to value then send user back to that screen
            if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
                header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
                
            // else send user to the default view
            } else {
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_users.php');
            }
            exit();
            
		// else user is not allowed to be deleted, so output error
        } else {
            $liveform_view_users->add_notice(lang('The user may not be deleted because it is the only administrator.'));
            
            // If there is a send to value then send user back to that screen
            if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
                header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
                
            // else send user to the default view
            } else {
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_users.php');
            }
            exit();
        }

    // else the user is being edited
    } else {

        // The role arrives as text. Cast it before the ceiling check so a
        // non-numeric value cannot slip past the comparison and reach the SQL,
        // where a lenient server would store it as 0 (administrator).
        if (isset($_POST['role'])) {
            $_POST['role'] = (int) $_POST['role'];

            if (in_array($_POST['role'], array(0, 1, 2, 3), true) == false) {
                log_activity(lang('access denied because user does not have access to set the requested role for a user'), $_SESSION['sessionusername']);
                output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }
        }

        // if editor is not an administrator and the editor's role is less than or equal to the role that the editor is trying to set, then output error
        if (($user['role'] != 0) && ($user['role'] >= $_POST['role'])) {
            log_activity(lang('access denied because user does not have access to set the requested role for a user'), $_SESSION['sessionusername']);
            output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        $_POST['username'] = trim($_POST['username']);
        $_POST['email'] = trim($_POST['email']);

        // If the username is blank, then output error.
        if ($_POST['username'] == '') {
            output_error(lang(array('string'=>'{var:1} is required','vars'=>lang('Username'))) . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
        }

        // validate e-mail address
        if (validate_email_address($_POST['email']) == FALSE) {
            output_error(lang(array('string'=>'{var:1} is invalid','vars'=>lang('The email address'))) . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
        }

        // determine if username is already in use
        $result = db_q("SELECT user_id FROM user WHERE ((user_username = ?) OR (user_email = ?)) AND (user_id != ?)", array((string) ($_POST['username'] ?? ''), (string) ($_POST['username'] ?? ''), (string) ($_POST['id'] ?? ''))) or output_error('Query failed');
        if (mysqli_num_rows($result) > 0)
        {
            output_error(lang('The username that you entered is already in use') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
        }

        // determine if e-mail address is already in use
        $result = db_q("SELECT user_id FROM user WHERE ((user_email = ?) OR (user_username = ?)) AND (user_id != ?)", array((string) ($_POST['email'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['id'] ?? ''))) or output_error('Query failed');
        if (mysqli_num_rows($result) > 0) {
            output_error(lang('The email address that you entered is already in use') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
        }

        $username = $_POST['username'];
        
        // get role for user that is being edited
        $query =
            "SELECT user_role
            FROM user
            WHERE user_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $role = $row['user_role'];
        
        // assume that role is allowed to be changed, until we find out otherwise
        $allow_role_change = true;
        
        // if the editor is an administrator and the user that is being edited is an administrator, find out if role is allowed to be changed
        if (($user['role'] == 0) && ($role == 0)) {
            // check to see if there is another administrator user, other than this user that is being edited
            $query =
                "SELECT user_id
                FROM user
                WHERE
                    (user_role = '0')
                    AND (user_id != ?)";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // if there is no other administrator user, do not allow the role to be changed
            if (mysqli_num_rows($result) == 0) {
                $allow_role_change = false;
            }
        }

        // if the role is allowed to be changed. The last administrator's role
        // cards are disabled and post nothing; an empty role must not reach
        // the column, where a lenient server would store it as 0.
        if (($allow_role_change == true) && isset($_POST['role'])) {
            $sql_role = " user_role = '" . escape($_POST['role']) . "',";
            $saved_role = (int) $_POST['role'];
        } else {
            $sql_role = "";
            $saved_role = (int) $role;
        }
        
        $sql_offline_payment = '';
        
        // if offline payment is enabled, then prepare to update set offline payment
        if (ECOMMERCE_OFFLINE_PAYMENT == TRUE) {
            $sql_offline_payment = "user_set_offline_payment = '" . escape($_POST['set_offline_payment'] ?? '') . "',";
        }
        
        $sql_set_page_types = '';
        
        // if forms are enabled, then set the sql to update the set page type columns that are associated with this feature
        if (FORMS == true) {
            $sql_set_page_types .= 
                " user_set_page_type_custom_form = '" . escape($_POST['set_page_type_custom_form'] ?? '') . "',
                user_set_page_type_custom_form_confirmation = '" . escape($_POST['set_page_type_custom_form_confirmation'] ?? '') . "', 
                user_set_page_type_form_list_view = '" . escape($_POST['set_page_type_form_list_view'] ?? '') . "',
                user_set_page_type_form_item_view = '" . escape($_POST['set_page_type_form_item_view'] ?? '') . "',
                user_set_page_type_form_view_directory = '" . escape($_POST['set_page_type_form_view_directory'] ?? '') . "',";
        }
        
        // if calendars are enabled, then set the sql to update the set page type columns that are associated with this feature
        if (CALENDARS == true) {
            $sql_set_page_types .= 
                " user_set_page_type_calendar_view = '" . escape($_POST['set_page_type_calendar_view'] ?? '') . "',
                user_set_page_type_calendar_event_view = '" . escape($_POST['set_page_type_calendar_event_view'] ?? '') . "',";
        }
        
        // if ecommerce is enabled, then set the sql to update the set page type columns that are associated with this feature
        if (ECOMMERCE == true) {
            $sql_set_page_types .= 
                " user_set_page_type_catalog = '" . escape($_POST['set_page_type_catalog'] ?? '') . "',
                user_set_page_type_catalog_detail = '" . escape($_POST['set_page_type_catalog_detail'] ?? '') . "',
                user_set_page_type_express_order = '" . escape($_POST['set_page_type_express_order'] ?? '') . "',
                user_set_page_type_order_form = '" . escape($_POST['set_page_type_order_form'] ?? '') . "',
                user_set_page_type_shopping_cart = '" . escape($_POST['set_page_type_shopping_cart'] ?? '') . "',
                user_set_page_type_shipping_address_and_arrival = '" . escape($_POST['set_page_type_shipping_address_and_arrival'] ?? '') . "',
                user_set_page_type_shipping_method = '" . escape($_POST['set_page_type_shipping_method'] ?? '') . "',
                user_set_page_type_billing_information = '" . escape($_POST['set_page_type_billing_information'] ?? '') . "',
                user_set_page_type_order_preview = '" . escape($_POST['set_page_type_order_preview'] ?? '') . "',
                user_set_page_type_order_receipt = '" . escape($_POST['set_page_type_order_receipt'] ?? '') . "',";
        }
        
        // The workspace rights, when their columns exist.
        $sql_ws_rights = pg_user_permission_ws_sql('set');

        // The ERP's read-only right, when its column exists.
        $sql_erp_readonly = pg_user_erp_readonly_sql('set');

        // The only write to the user row in this save; the rest go to the
        // access lists.
        $audit_before = pg_audit_fetch('user', $audit_user_id);

        // update user
        $query =
            "UPDATE user
            SET
                user_username = '" . escape($username) . "',
                user_email = '" . escape($_POST['email'] ?? '') . "',
                $sql_role
                user_home = '" . escape($_POST['home_page'] ?? '') . "',
                user_password_hint = '" . escape($_POST['password_hint'] ?? '') . "',
                user_badge = '" . escape($_POST['badge'] ?? '') . "',
                user_badge_label = '" . escape($_POST['badge_label'] ?? '') . "',
                user_reward_points = '" . escape($_POST['reward_points'] ?? '') . "',
                user_manage_contacts = '" . escape($_POST['manage_contacts'] ?? '') . "',
                user_manage_visitors = '" . escape($_POST['manage_visitors'] ?? '') . "',
                user_create_pages = '" . escape($_POST['create_pages'] ?? '') . "',
                user_delete_pages = '" . escape($_POST['delete_pages'] ?? '') . "',
                user_manage_forms = '" . escape($_POST['manage_forms'] ?? '') . "',
                user_manage_calendars = '" . escape($_POST['manage_calendars'] ?? '') . "',
                user_manage_emails = '" . escape($_POST['manage_emails'] ?? '') . "',
                user_manage_ecommerce = '" . escape($_POST['manage_ecommerce'] ?? '') . "',
                user_view_card_data = '" . escape($_POST['view_card_data'] ?? '') . "',
                manage_ecommerce_reports = '" . e($_POST['manage_ecommerce_reports'] ?? '') . "',
                manage_erp = '" . e($_POST['manage_erp'] ?? '') . "',
                manage_erp_cash = '" . e($_POST['manage_erp_cash'] ?? '') . "',
                manage_erp_settings = '" . e($_POST['manage_erp_settings'] ?? '') . "',
                $sql_erp_readonly
                $sql_ws_rights
                $sql_offline_payment
                user_publish_calendar_events = '" . escape($_POST['publish_calendar_events'] ?? '') . "',
                user_set_page_type_email_a_friend = '" . escape($_POST['set_page_type_email_a_friend'] ?? '') . "',
                user_set_page_type_folder_view = '" . escape($_POST['set_page_type_folder_view'] ?? '') . "',
                user_set_page_type_photo_gallery = '" . escape($_POST['set_page_type_photo_gallery'] ?? '') . "',
                $sql_set_page_types
                user_user = '" . $user['id'] . "',
                user_timestamp = UNIX_TIMESTAMP()
            WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        pg_audit_record('update', 'user', $audit_user_id, $audit_before);

        pg_user_form_save_access($_POST['id'] ?? '', $saved_role, $_POST, true);

        log_activity(lang(array('string'=>'user ({var:1}) was modified','vars'=>$username)), $_SESSION['sessionusername']);
        $liveform_view_users->add_notice(lang('The user has been saved.'));
        
        // If there is a send to value then send user back to that screen
        if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
            
        // else send user to the default view
        } else {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_users.php');
        }
        
        exit();
    }
}