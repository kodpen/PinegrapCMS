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
$liveform_view_users = new liveform('view_users');
$user = validate_user();
validate_area_access($user, 'manager');

// if editor is less than an administrator role, check to make sure that editor has access to edit user
if ($user['role'] > 0) {
    // get role of user that is being edited
    $query =
        "SELECT user_role
        FROM user
        WHERE user_id = '" . escape($_REQUEST['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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
if (isset($_POST['pg_unlink_google'])) {

    validate_token_field();

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
                'string' => 'access denied to disconnect Google for a higher-role user ({var:1})',
                'vars'   => array($unlink_user['user_username']))), USER_USERNAME);
            output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        // An account with no password of its own would be locked out entirely.
        if (((int) $unlink_user['user_password_algo']) === 3) {
            output_error(lang('This account signs in with Google only. Give it a password first, or disconnecting Google would lock it out.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        db("UPDATE user SET user_google_id = NULL WHERE user_id = '" . $unlink_user_id . "'");

        log_activity(lang(array(
            'string' => 'google connection removed from user ({var:1})',
            'vars'   => array($unlink_user['user_username']))), $_SESSION['sessionusername'] ?? '');
    }

    $unlink_send_to = '';

    if ((isset($_POST['send_to'])) && ($_POST['send_to'] != '')) {
        $unlink_send_to = '&send_to=' . urlencode($_POST['send_to']);
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY
        . '/edit_user.php?id=' . urlencode($_POST['id'] ?? '') . $unlink_send_to);

    exit();
}

if (!$_POST) {
    $set_page_type_values = array();
    
    $query =
        "SELECT
            user_id,
            user_username,
            user_email,
            user_google_id,
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
        WHERE user_id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $id = $row['user_id'];
    $username = $row['user_username'];
    $email = $row['user_email'];
    $user_google_id = $row['user_google_id'];
    $output_google_badge = (!empty($user_google_id))
        ? ' <span class="badge bg-light text-dark border fw-light" title="' . h(lang('This account can sign in with Google.')) . '">' . lang('Google') . '</span>'
        : '';
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
        $output_login_as_user_button = '<a class="btn btn-sm btn-outline-secondary rounded-pill px-3" href="login_as_user.php?id=' . h($_GET['id']) . get_token_query_string_field() . '"><i class="bi bi-box-arrow-in-right me-1"></i>' . lang('Login as User') . '</a>';
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
        $output_reset_password_button = '<a class="btn btn-sm btn-outline-warning rounded-pill px-3" href="#" onclick="event.preventDefault(); pgConfirm({title:\'' . lang('Reset & Send Password') . '\', message:\'' . lang('Are you sure you want to reset the user\'s password and email a new password to the user?') . '\', confirmText:\'' . lang('Reset & Send Password') . '\', cancelText:\'' . lang('Cancel') . '\', variant:\'warning\'}).then(function(ok){if(ok) document.reset_password_form.submit();}); return false;"><i class="bi bi-key me-1"></i>' . lang('Reset & Send Password') . '</a>';

        $output_reset_password_form =
            '<form name="reset_password_form" action="reset_password.php" method="post">'
            . get_token_field()
            . '<input type="hidden" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />'
            . '<input type="hidden" name="user_id" value="' . h($_GET['id']) . '">'
            . '</form>';

    } else {
        $output_reset_password_button = '<span class="text-body-secondary small">' . h(lang('You cannot reset your own password here.')) . '</span>';

        // Linked only where the site actually has a change password page: a
        // button pointing at a page that is not there is worse than no button.
        $own_password_url = get_page_type_url('change password');

        if ($own_password_url) {
            $output_reset_password_button .= '<a class="btn btn-sm btn-outline-secondary rounded-pill px-3" href="' . h($own_password_url) . '"><i class="bi bi-key me-1"></i>' . lang('Change Password') . '</a>';
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
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-danger" data-loading-content="' . lang(array('string'=>'Deleting')) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('user')))) . '"><i class="bi bi-trash me-2"></i><span class="btn-text">' . lang(array('string'=>'Delete')) . '</span></button>';
    } else {
        $output_delete_button = '<button type="submit" name="submit_delete" class="btn my-1 btn-danger disabled"><i class="bi bi-trash me-2"></i><span class="btn-text">' . lang(array('string'=>'Delete')) . '</span></button>';
    }

    $output_password_hint_field = "";

    // Find out if password hint is enabled in the settings
    if (PASSWORD_HINT == TRUE) {
        $output_password_hint_field = 
            '<div class="col-12 col-xl-6 my-2">
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

    $result = mysqli_query(db::$con, "SELECT aclfolder_folder as folder_id FROM aclfolder
        WHERE (aclfolder_user = '" . escape($_GET['id']) . "') AND (aclfolder_rights = '2')") or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $folders_that_user_has_edit_access_to[] = $row['folder_id'];
    }

    $folders_that_user_has_view_access_to = array();

    $result = mysqli_query(db::$con, "SELECT aclfolder_folder as folder_id FROM aclfolder
        WHERE (aclfolder_user = '" . escape($_GET['id']) . "') AND (aclfolder_rights = '1')") or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $folders_that_user_has_view_access_to[] = $row['folder_id'];
    }

    $common_regions_user_can_edit = get_items_user_can_edit('common_regions', $_GET['id']);
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

            $output_calendars .= '<div class="form-check"><input type="checkbox" name="calendar_' . $calendar['id'] . '" id="calendar_' . $calendar['id'] . '" value="1" class="form-check-input multiselect-checkbox"' . (($calendar_checked != '') ? ' checked="checked"' : '') . ' /><label class="form-check-label" for="calendar_' . $calendar['id'] . '"> ' . h($calendar['name']) . '</label></div>';
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
            . pg_user_permission_switch('manage_erp_settings', '1', ($manage_erp_settings == 1), lang('Change ERP settings'), lang('Numbering, default accounts and the Parasut connection.'));
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

        $output_contact_groups .= '<div class="form-check"><input type="checkbox" name="contact_group_' . $contact_group['id'] . '" id="contact_group_' . $contact_group['id'] . '" value="1" class="form-check-input multiselect-checkbox"' . (($contact_group_checked != '') ? ' checked="checked"' : '') . ' /><label class="form-check-label" for="contact_group_' . $contact_group['id'] . '">' . h($contact_group['name']) . '</label></div>';
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
        ),
        'panels' => $permission_panels,
        'counts' => array(
            'edit_folders'   => count($folders_that_user_has_edit_access_to),
            'view_folders'   => count($folders_that_user_has_view_access_to),
            'page_types'     => $page_type_count,
            'common_regions' => count($common_regions_user_can_edit),
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

    $edit_user_pin_column = (bool) db_item("SHOW COLUMNS FROM auth_tokens LIKE 'pinned'");

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
                $edit_user_session_tags .= '<span class="badge text-bg-success fw-light">' . h(lang('This device')) . '</span>';
            }

            if ((time() - $edit_user_session_fresh) < 300) {
                $edit_user_session_tags .= '<span class="badge text-bg-success fw-light">' . h(lang('Online')) . '</span>';
            }

            $edit_user_session_pinned = ($edit_user_pin_column && isset($edit_user_session['pinned']) && (((int) $edit_user_session['pinned']) === 1));

            if ($edit_user_session_pinned) {
                $edit_user_session_tags .= '<span class="badge text-bg-secondary fw-light" title="' . h(lang('Locked by the site owner: the device limit cannot evict it and the member cannot sign it out.')) . '"><i class="bi bi-pin-angle-fill"></i> ' . h(lang('Locked')) . '</span>';
            }

            $edit_user_session_pin_button = '';

            if ($edit_user_pin_column) {
                $edit_user_session_pin_button =
                    '<form method="post" action="view_sessions.php" style="margin:0">' . get_token_field()
                    . '<input type="hidden" name="pg_session_action" value="' . ($edit_user_session_pinned ? 'unpin' : 'pin') . '"/>'
                    . '<input type="hidden" name="pg_selector" value="' . h((string) $edit_user_session['selector']) . '"/>'
                    . '<input type="hidden" name="send_back" value="' . h($edit_user_sessions_back) . '"/>'
                    . '<button type="submit" class="btn btn-sm btn-ghost" title="' . h($edit_user_session_pinned ? lang('Unlock') : lang('Lock')) . '"><i class="bi ' . ($edit_user_session_pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle') . '"></i></button>'
                    . '</form>';
            }

            $edit_user_session_rows .=
                '<div class="pg-session">'
                . '<i class="bi ' . (preg_match('/(iphone|android|mobile|ipad)/i', (string) $edit_user_session['user_agent']) ? 'bi-phone' : 'bi-laptop') . '"></i>'
                . '<div class="pg-session-text">'
                . '<div class="pg-session-name">' . h(pg_user_agent_label($edit_user_session['user_agent'])) . $edit_user_session_tags . '</div>'
                . '<div class="pg-session-meta">' . h((string) $edit_user_session['ip_address'])
                . ' &middot; ' . h(lang(array('string' => 'Opened {var:1}', 'vars' => date('Y-m-d H:i', (int) $edit_user_session['created_at']))))
                . ' &middot; ' . h(lang(array('string' => 'Expires {var:1}', 'vars' => date('Y-m-d H:i', (int) $edit_user_session['expires_at']))))
                . '</div></div>'
                . '<div class="pg-session-actions">' . $edit_user_session_pin_button
                . '<form method="post" action="view_sessions.php" style="margin:0">' . get_token_field()
                . '<input type="hidden" name="pg_session_action" value="revoke"/>'
                . '<input type="hidden" name="pg_selector" value="' . h((string) $edit_user_session['selector']) . '"/>'
                . '<input type="hidden" name="send_back" value="' . h($edit_user_sessions_back) . '"/>'
                . '<button type="submit" class="btn btn-sm btn-outline-danger">' . h(lang('Sign out')) . '</button>'
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

        $output_identity_lock_tag = '<span class="badge text-bg-danger fw-light"><i class="bi bi-lock-fill me-1"></i>' . h(lang('Sign-in Locked')) . '</span>';

        $output_sign_in_lock_notice =
            '<div class="alert alert-danger d-flex align-items-center gap-2 py-2">'
            . '<i class="bi bi-lock-fill"></i>'
            . '<span><b>' . h(lang('Sign-in is locked.')) . '</b> ' . h(lang(array(
                'string' => 'Too many failed attempts. Clears on its own in {var:1} minutes.',
                'vars'   => $sign_in_lock_minutes))) . '</span>'
            . '<a class="btn btn-sm btn-outline-danger rounded-pill px-3 ms-auto" href="#" onclick="event.preventDefault(); document.unlock_sign_in_form.submit(); return false;"><i class="bi bi-unlock me-1"></i>' . h(lang('Unlock Sign-in')) . '</a>'
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
        '<span class="badge text-bg-primary fw-light">' . h(pg_user_role_name($role)) . '</span>'
        . ((!empty($user_google_id)) ? '<span class="badge text-bg-light border fw-light" title="' . h(lang('This account can sign in with Google.')) . '"><i class="bi bi-google me-1"></i>Google</span>' : '')
        . ((($edit_user_session_latest > 0) && ((time() - $edit_user_session_latest) < 300)) ? '<span class="badge text-bg-success fw-light">' . h(lang('Online')) . '</span>' : '')
        . $output_identity_lock_tag;

    // ── Contact card ─────────────────────────────────────────────────────
    if ($contact_id != '') {

        $output_contact_lines = '<span>' . h($email_address) . '</span>';

        if ($member_id != '') {
            $output_contact_lines .= '<span>' . h(MEMBER_ID_LABEL) . ': ' . h($member_id) . '</span>';
        }

        $output_contact_body =
            '<a class="pg-contact-card link-body-emphasis text-decoration-none" href="edit_contact.php?id=' . (int) $contact_id . '&send_to=' . h(urlencode(get_request_uri())) . '">'
            . (($identity_photo != '')
                ? '<img class="pg-contact-pic" src="' . h($identity_photo) . '" alt="" />'
                : '<span class="pg-contact-pic d-inline-flex align-items-center justify-content-center bg-secondary-subtle text-body-secondary fs-4"><i class="bi bi-person"></i></span>')
            . '<span class="pg-contact-who"><b>' . h($identity_name) . '</b>' . $output_contact_lines . '</span>'
            . '<i class="bi bi-chevron-right ms-auto text-body-secondary"></i>'
            . '</a>';

        $output_contact_header =
            '<a class="btn btn-sm btn-ghost" href="edit_contact.php?id=' . (int) $contact_id . '&send_to=' . h(urlencode(get_request_uri())) . '">' . h(lang('Open contact')) . '</a>';

    } else {

        $output_contact_body =
            '<p class="small text-body-secondary mb-2">' . h(lang('This User does not currently have a Contact connected to it. Connecting a Contact to this User is required to use the Membership features. A Contact will be created and connected to this User automatically when the User performs certain actions (e.g. updates his/her profile, submits a Custom Form, submits an Order, and etc.). However, if for any reason, you would like to do this now, you can.')) . '</p>'
            . '<button type="submit" id="submit_create_contact" name="submit_create_contact" value="Create Contact" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-loading-content="' . h(lang('Creating')) . '"><i class="bi bi-person-plus me-1"></i><span class="btn-text">' . h(lang('Create Contact')) . '</span></button>';

        $output_contact_header = '';
    }

    $output_contact_card =
        '<div class="card mb-3">
            <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Contact')) . '</span>
                ' . $output_contact_header . '
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
        ? lang(array('string' => 'Last changed {var:1}', 'vars' => date('d.m.Y', $password_changed_at)))
        : lang('Change date not recorded');

    $output_google_row = (!empty($user_google_id))
        ? '<div class="pg-fact">
                <span class="pg-fact-icon"><i class="bi bi-google"></i></span>
                <div class="pg-fact-text"><b>' . h(lang('Sign in with Google')) . '</b><span>' . h(lang('Connected')) . '</span></div>
            </div>'
        : '<div class="pg-fact">
                <span class="pg-fact-icon"><i class="bi bi-google"></i></span>
                <div class="pg-fact-text"><b>' . h(lang('Sign in with Google')) . '</b><span>' . h(lang('Not connected')) . ' &middot; ' . h(lang('Signs in with a password only')) . '</span></div>
            </div>';

    $output_sessions_row = ($edit_user_sessions_count > 0)
        ? '<div class="pg-fact">
                <span class="pg-fact-icon"><i class="bi bi-laptop"></i></span>
                <div class="pg-fact-text"><b>' . h(lang(array('string' => '{var:1} active session{suffix:1}', 'vars' => $edit_user_sessions_count, 'suffix' => (($edit_user_sessions_count == 1) ? '' : 's')))) . '</b><span>' . h(lang('Last used')) . ' ' . get_relative_time(array('timestamp' => $edit_user_session_latest)) . '</span></div>
                <button type="button" class="btn btn-sm btn-ghost pg-fact-action" data-bs-toggle="offcanvas" data-bs-target="#pg_sessions_panel">' . h(lang('Manage')) . '<i class="bi bi-chevron-right ms-1"></i></button>
            </div>'
        : '<div class="pg-fact">
                <span class="pg-fact-icon"><i class="bi bi-laptop"></i></span>
                <div class="pg-fact-text"><b>' . h(lang('No active sessions')) . '</b></div>
                ' . ((!empty($user_google_id)) ? '<button type="button" class="btn btn-sm btn-ghost pg-fact-action" data-bs-toggle="offcanvas" data-bs-target="#pg_sessions_panel">' . h(lang('Manage')) . '<i class="bi bi-chevron-right ms-1"></i></button>' : '') . '
            </div>';

    $output_sign_in_card =
        '<div class="card mb-3">
            <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Sign-in and Sessions')) . '</span>
            </div>
            <div class="card-body">
                <div class="pg-fact">
                    <span class="pg-fact-icon"><i class="bi bi-key"></i></span>
                    <div class="pg-fact-text"><b>' . h(lang('Password')) . '</b><span>' . h($output_password_note) . '</span></div>
                </div>
                ' . $output_google_row . $output_sessions_row . '
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
        '<div class="card mb-3">
            <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Recent Activity')) . '</span>
                ' . (($log_count > 5) ? '<button type="button" class="btn btn-sm btn-ghost" data-bs-toggle="offcanvas" data-bs-target="#pg_activity_panel">' . h(lang(array('string' => 'All ({var:1})', 'vars' => $log_count))) . '</button>' : '') . '
            </div>
            <div class="card-body">
                ' . (($log_count > 0) ? $log_recent_rows : '<p class="small text-body-secondary mb-0">' . h(lang('No activity recorded yet.')) . '</p>') . '
            </div>
        </div>';

    // ── The panels that live outside the form ────────────────────────────
    //
    // Sessions and the Google connection are their own forms, and forms cannot
    // nest, so these sit after the main form closes. The panel says as much:
    // the buttons in it act at once rather than on Save.
    $output_google_panel = (!empty($user_google_id))
        ? '<div class="pg-perm-block">
                <div class="pg-perm-block-title">' . h(lang('Sign in with Google')) . '</div>
                <div class="d-flex align-items-start gap-3 border rounded p-3">
                    <span class="pg-fact-icon"><i class="bi bi-google"></i></span>
                    <div class="flex-grow-1">
                        <p class="small mb-0">' . h(lang('The connection is tied to the Google account itself, not to the email address - changing the email here does not remove it.')) . '</p>
                    </div>
                    <form method="post" action="edit_user.php" style="margin:0">' . get_token_field()
                        . '<input type="hidden" name="pg_unlink_google" value="1"/>'
                        . '<input type="hidden" name="id" value="' . h($_GET['id']) . '"/>'
                        . '<input type="hidden" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '"/>'
                        . '<button type="submit" class="btn btn-sm btn-outline-danger">' . h(lang('Disconnect Google')) . '</button>'
                    . '</form>
                </div>
            </div>'
        : '';

    $output_sessions_list = ($edit_user_sessions_count > 0)
        ? '<div class="pg-perm-block">
                <div class="pg-perm-block-title">' . h(lang(array('string' => '{var:1} active session{suffix:1}', 'vars' => $edit_user_sessions_count, 'suffix' => (($edit_user_sessions_count == 1) ? '' : 's')))) . '</div>
                <div class="card"><div class="card-body p-0">' . $edit_user_session_rows . '</div></div>
                <form method="post" action="view_sessions.php" class="mt-2">' . get_token_field()
                    . '<input type="hidden" name="pg_session_action" value="revoke_user"/>'
                    . '<input type="hidden" name="pg_user_id" value="' . (int) $_GET['id'] . '"/>'
                    . '<input type="hidden" name="send_back" value="' . h($edit_user_sessions_back) . '"/>'
                    . '<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>' . h(lang('Sign out all sessions')) . '</button>'
                . '</form>
            </div>'
        : '<p class="small text-body-secondary">' . h(lang('No active sessions.')) . '</p>';

    $output_sessions_panel =
        '<div class="offcanvas offcanvas-end pg-perm-panel" tabindex="-1" id="pg_sessions_panel" aria-labelledby="pg_sessions_panel_title">
            <div class="offcanvas-header">
                <span class="pg-perm-icon" style="--pg-perm-color:#64b5f6"><i class="bi bi-shield-lock"></i></span>
                <h5 class="offcanvas-title ms-2" id="pg_sessions_panel_title">' . h(lang('Sign-in and Sessions')) . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="offcanvas-body">
                <p class="pg-perm-panel-lead">' . h(lang('These act at once and do not wait for Save.')) . '</p>
                ' . $output_sessions_list . $output_google_panel . '
            </div>
            <div class="offcanvas-footer">
                <div class="pg-perm-panel-count">' . h(lang('Sessions expire on their own after 30 days.')) . '</div>
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-dismiss="offcanvas">' . h(lang('Done')) . '</button>
            </div>
        </div>'
        . (($log_count > 5)
            ? '<div class="offcanvas offcanvas-end pg-perm-panel" tabindex="-1" id="pg_activity_panel" aria-labelledby="pg_activity_panel_title">
                <div class="offcanvas-header">
                    <span class="pg-perm-icon" style="--pg-perm-color:#9aa3ab"><i class="bi bi-clock-history"></i></span>
                    <h5 class="offcanvas-title ms-2" id="pg_activity_panel_title">' . h(lang('Recent Activity')) . '</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' . h(lang('Close')) . '"></button>
                </div>
                <div class="offcanvas-body">' . $log_panel_rows . '</div>
            </div>'
            : '');

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
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
            <div class="row">
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-body py-3">
                        <div class="pg-identity">
                            ' . $output_identity_picture . '
                            <div class="pg-identity-who">
                                <div class="pg-identity-name">' . h($identity_name) . $output_identity_tags . '</div>
                                <div class="pg-identity-meta">' . h($username) . ' &middot; ' . h($email) . '</div>
                            </div>
                            <div class="pg-identity-actions">
                                ' . $output_login_as_user_button . '
                                ' . $output_reset_password_button . '
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
                    <div class="row g-3">
                        <div class="col-12 col-xl-8">

                            <div class="card mb-3">
                                <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                                    <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Role') . '</span>
                                    <span class="small text-body-secondary">' . lang('Changing the role changes the rights below.') . '</span>
                                </div>
                                <div class="card-body">
                                    ' . pg_user_role_cards($role, $user['role'], $role_cards_disabled) . '
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                                    <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Account') . '</span>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12 col-lg-4">
                                            <label for="username" class="form-label">' . lang('Username') . '</label>
                                            <input value="' . h($username) . '" type="text" name="username" placeholder="' . lang('Username') . '" id="username" maxlength="100" class="form-control" required />
                                            <div class="form-text">' . lang('Used when signing in.') . '</div>
                                        </div>
                                        <div class="col-12 col-lg-4">
                                            <label class="form-label" for="email">' . lang('User Email') . '</label>
                                            <input value="' . h($email) . '" type="email" class="form-control" id="email" name="email" maxlength="100" inputmode="email" data-inputmask-alias="email" required/>
                                            <div class="form-text">' . lang('Password reset goes to this address.') . '</div>
                                        </div>
                                        <div class="col-12 col-lg-4">
                                            <label for="home_page" class="form-label">' . lang('User Start Page') . '</label>
                                            <select class="form-select" name="home_page" id="home_page"><option value="0">[' . lang('None') . ']</option>' . select_page($home) . '</select>
                                            <div class="form-text">' . lang('Opened after signing in.') . '</div>
                                        </div>
                                    </div>

                                    <hr class="border-secondary-subtle opacity-50 my-3">
                                    <div class="text-uppercase small fw-semibold text-body-secondary mb-2">' . lang('Optional') . '</div>

                                    <div class="row g-3">
                                        ' . $output_password_hint_field . '
                                        <div class="col-12 col-sm-6 col-lg-4">
                                            <label class="form-label" for="reward_points">' . lang('Reward Program, Reward Points') . '</label>
                                            <input value="' . h($reward_points) . '" type="number" class="form-control" id="reward_points" name="reward_points" maxlength="9"/>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input type="checkbox"' . $badge_checked . ' id="badge" name="badge" value="1" class="form-check-input collapse-switcher" data-bs-target="#badge_row"/>
                                                <label class="form-check-label" for="badge">' . lang('Show badge next to username') . '</label>
                                            </div>
                                            <div class="collapse ms-4 mt-1 mb-2' . (($badge_checked != '') ? ' show' : '') . '" id="badge_row" style="max-width:22rem">
                                                <input value="' . h($badge_label) . '" type="text" name="badge_label" placeholder="' . $output_badge_label_placeholder . '" id="badge_label" class="form-control form-control-sm" size="20" maxlength="100" />
                                                ' . $output_badge_label_info . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3 d-none" id="pg_permissions_everything">
                                <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                                    <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('User Rights') . '</span>
                                </div>
                                <div class="card-body">
                                    ' . pg_user_permission_everything() . '
                                </div>
                            </div>

                            <div class="card mb-3" id="pg_permissions_block">
                                <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                                    <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('User Rights') . '</span>
                                    <span class="small text-body-secondary" data-pg-tally="' . h(lang(array('string' => '{var:1} of {var:2} areas on', 'vars' => array('{n}', '{m}')))) . '"></span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="pg-perms" id="pg_permissions" data-pg-empty="' . h(lang('No selection')) . '">' . $permission_ui['rows'] . '
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-4">
                            ' . $output_contact_card . '
                            ' . $output_sign_in_card . '
                            ' . $output_activity_card . '
                        </div>
                    </div>

                    ' . $permission_ui['panels'] . '

                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="bi bi-floppy me-2"></span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
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
    
    // if create contact was selected, then create contact
    if (($_POST['submit_create_contact'] ?? '') == 'Create Contact') {
        // get e-mail address for this user
        $query =
            "SELECT user_email
            FROM user
            WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $email_address = $row['user_email'];
        
        $query =
            "INSERT INTO contacts (
                email_address,
                user,
                timestamp)
            VALUES (
                '" . escape($email_address) . "',
                '" . $user['id'] . "',
                UNIX_TIMESTAMP())";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        $contact_id = mysqli_insert_id(db::$con);
        
        // connect contact to user
        $query =
            "UPDATE user
            SET
                user_contact = '" . $contact_id . "',
                user_user = '" . $user['id'] . "',
                user_timestamp = UNIX_TIMESTAMP()
            WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // forward user to contact that was just created
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id . '&send_to=' . urlencode($_POST['current_url']));
        exit();
    
    // else if user was selected for delete
    } else if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // get role for user that is being deleted
        $query =
            "SELECT user_role
            FROM user
            WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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
                    AND (user_id != '" . escape($_POST['id'] ?? '') . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if there is no other administrator user, do not allow user to be deleted
            if (mysqli_num_rows($result) == 0) {
                $allow_delete = false;
            }
        }

        // if the user is allowed to be deleted
        if ($allow_delete == true) {
            // Revoke sign-in tokens first, as delete_users.php does, so no token
            // outlives the user row it belongs to.
            pg_auth_token_revoke_user((int) ($_POST['id'] ?? 0));

            // delete user record
            $query = "DELETE FROM user WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete aclfolder records
            $query = "DELETE FROM aclfolder WHERE aclfolder_user = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete users_contact_groups_xref records
            $query = "DELETE FROM users_contact_groups_xref WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete users_calendars_xref records
            $query = "DELETE FROM users_calendars_xref WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete address book records
            $query = "DELETE FROM address_book WHERE user = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete ad region xref records
            $query = "DELETE FROM users_ad_regions_xref WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete common region xref records
            $query = "DELETE FROM users_common_regions_xref WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete menu xref records
            $query = "DELETE FROM users_menus_xref WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            db("DELETE FROM users_messages_xref WHERE user_id = '" . e($_POST['id'] ?? '') . "'");
            
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
        $result = mysqli_query(db::$con, "SELECT user_id FROM user WHERE ((user_username = '" . escape($_POST['username'] ?? '') . "') OR (user_email = '" . escape($_POST['username'] ?? '') . "')) AND (user_id != '" . escape($_POST['id'] ?? '') . "')") or output_error('Query failed');
        if (mysqli_num_rows($result) > 0)
        {
            output_error(lang('The username that you entered is already in use') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
        }

        // determine if e-mail address is already in use
        $result = mysqli_query(db::$con, "SELECT user_id FROM user WHERE ((user_email = '" . escape($_POST['email'] ?? '') . "') OR (user_username = '" . escape($_POST['email'] ?? '') . "')) AND (user_id != '" . escape($_POST['id'] ?? '') . "')") or output_error('Query failed');
        if (mysqli_num_rows($result) > 0) {
            output_error(lang('The email address that you entered is already in use') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
        }

        $username = $_POST['username'];
        
        // get role for user that is being edited
        $query =
            "SELECT user_role
            FROM user
            WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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
                    AND (user_id != '" . escape($_POST['id'] ?? '') . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if there is no other administrator user, do not allow the role to be changed
            if (mysqli_num_rows($result) == 0) {
                $allow_role_change = false;
            }
        }

        // if the role is allowed to be changed
        if ($allow_role_change == true) {
            $sql_role = " user_role = '" . escape($_POST['role'] ?? '') . "',";
        } else {
            $sql_role = "";
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

        // Delete current access control records in order to add new ones.
        $query = "DELETE FROM aclfolder WHERE aclfolder_user = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // find all folders to update access control list
        $result=mysqli_query(db::$con, "SELECT folder_id FROM folder") or output_error('Query failed');
        while ($row=mysqli_fetch_array($result)) {
            $folder_id = $row['folder_id'];
            $sql_expiration_date = "";
            
            // if the user was given edit rights to this folder, then set rights value to 2
            if (($_POST['edit_' . $folder_id] ?? '') == 1) {
                $rights = 2;
                
            // else if the user was given view rights to this folder, then deal with that.
            } elseif (($_POST['view_' . $folder_id] ?? '') == 1) {
                // Remove spaces from beginning and end of date.
                $expiration_date = trim($_POST['view_' . $folder_id . '_expiration_date'] ?? '');

                // If an expiration date was entered, then validate it.
                if ($expiration_date != '') {
                    // Convert date to storage format.
                    $expiration_date = prepare_form_data_for_input($expiration_date, 'date');
                    
                    // Split date into parts.
                    $expiration_date_parts = explode('-', $expiration_date);
                    $year = $expiration_date_parts[0];
                    $month = $expiration_date_parts[1];
                    $day = $expiration_date_parts[2];
                    
                    // If the expiration date is valid, then give user access and set expiration date in SQL.
                    if ((is_numeric($month) == true) && (is_numeric($day) == true) && (is_numeric($year) == true) && (checkdate($month, $day, $year) == true)) {
                        $rights = 1;
                        $sql_expiration_date = $expiration_date;
                        
                    // Otherwise the expiration date is not valid, so do not give user access and do not set expiration date in SQL.
                    } else {
                        $rights = 0;
                    }

                // Otherwise an expiration date was not entered, so just give user access.
                } else {
                    $rights = 1;
                }
                
            // else the user was was given no rights to this folder, so set rights value to 0
            } else {
                $rights = 0;
            }

            $result2 = mysqli_query(db::$con, "INSERT INTO aclfolder (aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date) VALUES ('" . escape($_POST['id'] ?? '') . "', '" . $folder_id . "', '" . $rights . "', '" . escape($sql_expiration_date) . "')") or output_error('Query failed.');
        }
        
        // if user that was created has a user role, then prepare to assign access to various items for user
        if ($_POST['role'] == 3) {
            // delete user and common region references in users_common_regions_xref
            $query = "DELETE FROM users_common_regions_xref WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // get all common regions
            $query = "SELECT cregion_id FROM cregion";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $common_regions = array();
            
            while ($row = mysqli_fetch_assoc($result)) {
                $common_regions[] = $row;
            }
            
            // loop through all common regions and input them if they were selected
            foreach ($common_regions as $common_region) {
                // if common region was selected for user to be given access to, give access to common region
                if (($_POST['common_region_' . $common_region['cregion_id']] ?? '') == 1) {
                    $query =
                        "INSERT INTO users_common_regions_xref (
                            user_id,
                            common_region_id)
                        VALUES (
                            '" . escape($_POST['id'] ?? '') . "',
                            '" . escape($common_region['cregion_id']) . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
            
            // delete user and menu references in users_menus_xref
            $query = "DELETE FROM users_menus_xref WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // get all menus
            $query = "SELECT id FROM menus";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $menus = array();
            
            while ($row = mysqli_fetch_assoc($result)) {
                $menus[] = $row;
            }
            
            // loop through all menus and input them if they were selected
            foreach ($menus as $menu) {
                // if menu was selected for user to be given access to, give access to menu
                if (($_POST['menu_' . $menu['id']] ?? '') == 1) {
                    $query =
                        "INSERT INTO users_menus_xref (
                            user_id,
                            menu_id)
                        VALUES (
                            '" . escape($_POST['id'] ?? '') . "',
                            '" . escape($menu['id']) . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
            
            // delete user and contact group references in users_contact_groups_xref
            $query = "DELETE FROM users_contact_groups_xref WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if manage contacts or manage e-mails was checked, check to see which contact groups the user needs to be given access to
            if ((($_POST['manage_contacts'] ?? '') == 'yes') || (($_POST['manage_emails'] ?? '') == 'yes')) {
                // get all contact groups
                $query = "SELECT id FROM contact_groups";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                $contact_groups = array();
                
                while ($row = mysqli_fetch_assoc($result)) {
                    $contact_groups[] = $row;
                }
                
                // loop through all contact groups
                foreach ($contact_groups as $contact_group) {
                    // if contact group was selected for user to be given access to, give access to user to contact group
                    if (($_POST['contact_group_' . $contact_group['id']] ?? '') == 1) {
                        $query =
                            "INSERT INTO users_contact_groups_xref (
                                user_id,
                                contact_group_id)
                            VALUES (
                                '" . escape($_POST['id'] ?? '') . "',
                                '" . $contact_group['id'] . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
            }
            
            // delete user and calendar references in users_calendars_xref
            $query = "DELETE FROM users_calendars_xref WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if manage calendars was checked, check to see which calendars the user needs to be given access to
            if (($_POST['manage_calendars'] ?? '') == 'yes') {
                // get all calendars
                $query = "SELECT id FROM calendars";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                $calendars = array();
                
                while ($row = mysqli_fetch_assoc($result)) {
                    $calendars[] = $row;
                }
                
                // loop through all calendars
                foreach ($calendars as $calendar) {
                    // if calendar was selected for user to be given access to, give access to user to calendar
                    if (($_POST['calendar_' . $calendar['id']] ?? '') == 1) {
                        $query =
                            "INSERT INTO users_calendars_xref (
                                user_id,
                                calendar_id)
                            VALUES (
                                '" . escape($_POST['id'] ?? '') . "',
                                '" . $calendar['id'] . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
            }

            // If ads are enabled, then store access.
            if (ADS === true) {
                // delete user and ad region references in users_ad_regions_xref
                $query = "DELETE FROM users_ad_regions_xref WHERE user_id = '" . escape($_POST['id'] ?? '') . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                // get all ad regions
                $query = "SELECT id FROM ad_regions";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                $ad_regions = array();
                
                while ($row = mysqli_fetch_assoc($result)) {
                    $ad_regions[] = $row;
                }
                
                // loop through all ad regions and input them if they were selected
                foreach ($ad_regions as $ad_region) {
                    // if ad region was selected for user to be given access to, give access to ad region
                    if (($_POST['ad_region_' . $ad_region['id']] ?? '') == 1) {
                        $query =
                            "INSERT INTO users_ad_regions_xref (
                                user_id,
                                ad_region_id)
                            VALUES (
                                '" . escape($_POST['id'] ?? '') . "',
                                '" . escape($ad_region['id']) . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
            }
        }
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