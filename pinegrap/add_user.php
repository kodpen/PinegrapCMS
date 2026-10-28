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
$user = validate_user();
validate_area_access($user, 'manager');

// The contact is one value; one sent as an array (?contact_id[]=x) counts as
// not given: it ended in an error page.
pg_request_single_values(array('contact_id'));

if (!$_POST) {
    $email_address = '';

    // If the editing user came from the edit contact screen,
    // then get email address for contact in order to prefill email field.
    if (isset($_GET['contact_id']) && ($_GET['contact_id'] != '')) {
        $email_address = db_value("SELECT email_address FROM contacts WHERE id = '" . escape($_GET['contact_id']) . "'");
    }


    
    // Selection markup for the permission panels. Built here rather than in
    // includes/user_permissions.php because the edit screen calls the same
    // generators with the user's current selections; the helper only lays out
    // what it is handed.
    $permission_panels = array(
        'edit_tree'      => get_acl_folder_tree('edit'),
        'page_types'     => get_page_type_checkboxes_and_labels(),
        'common_regions' => get_checkboxes_for_items_user_can_edit('common_regions'),
        'shared_components' => get_checkboxes_for_items_user_can_edit('shared_components'),
        'menus'          => get_checkboxes_for_items_user_can_edit('menus'),
        'view_tree'      => get_date_picker_format() . get_acl_folder_tree('view'),
    );

    if (FORMS === true) {
        $permission_panels['manage_forms_switch'] = pg_user_permission_switch(
            'manage_forms', 'yes', false, lang('Reach the data submitted through forms'));
    }

    if (CALENDARS === true) {

        $output_calendars = '';

        $result = mysqli_query(db::$con, "SELECT id, name FROM calendars ORDER BY name") or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            $output_calendars .= '<div class="form-field"><input type="checkbox" name="calendar_' . $row['id'] . '" id="calendar_' . $row['id'] . '" value="1" class="check multiselect-checkbox" /><label class="" for="calendar_' . $row['id'] . '"> ' . h($row['name']) . '</label></div>';
        }

        $permission_panels['calendars'] = $output_calendars;
    }

    if (ECOMMERCE === true) {

        $output_commerce_switches =
            pg_user_permission_switch('view_card_data', '1', false, lang('View card data'))
            . pg_user_permission_switch('manage_ecommerce_reports', '1', false, lang('Manage commerce reports'), lang('Order reports and the shipping report.'));

        if (ECOMMERCE_OFFLINE_PAYMENT == TRUE) {
            $output_commerce_switches .= pg_user_permission_switch('set_offline_payment', '1', false, lang('Set the offline payment option on orders'));
        }

        $permission_panels['commerce_switches'] = $output_commerce_switches;
    }

    if (defined('ERP_ENABLED') && ERP_ENABLED) {

        // manage_erp is the gate and is rendered by the row itself; only the two
        // rights that sit behind it belong in the panel.
        $permission_panels['erp_switches'] =
            pg_user_permission_switch('manage_erp_cash', '1', false, lang('See cash and bank'), lang('Balances, receipts and payments.'))
            . pg_user_permission_switch('manage_erp_settings', '1', false, lang('Change ERP settings'), lang('Numbering, default accounts and the Parasut connection.'))
            . (pg_user_has_erp_readonly_column() ? pg_user_permission_switch('manage_erp_readonly', '1', false, lang('Read only (accountant)'), lang('Every ERP screen to read and the accountant\'s pack to download; nothing can be issued, paid or changed.')) : '');
    }

    if (pg_user_permission_ws_available()) {
        $permission_panels['workspace_switches'] = pg_user_permission_ws_switches();
    }

    if (ADS === true) {
        $permission_panels['ad_regions'] = get_checkboxes_for_items_user_can_edit('ad_regions');
    }

    // Contact groups.
    $output_contact_groups = '';

    $result = mysqli_query(db::$con, "SELECT id, name FROM contact_groups ORDER BY name") or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $output_contact_groups .= '<div class="form-field"><input type="checkbox" name="contact_group_' . $row['id'] . '" id="contact_group_' . $row['id'] . '" value="1" class="check multiselect-checkbox" /><label class="" for="contact_group_' . $row['id'] . '">' . h($row['name']) . '</label></div>';
    }

    $permission_panels['contact_groups'] = $output_contact_groups;

    // A new account starts with nothing selected, so every gate starts off and
    // the two page-creation rights keep the defaults the old screen had.
    $permission_ui = pg_user_permission_ui(array(
        'values' => array('create_pages' => '1', 'delete_pages' => '1', 'publish_calendar_events' => 'yes'),
        'panels' => $permission_panels,
        'counts' => array(),
    ));

    $output_hidden_role = '';
    $role_cards_disabled = false;

    // A manager may only create users, so the picker is read-only and the role
    // travels in a hidden field - a disabled control posts nothing.
    if ($user['role'] == 2) {
        $output_hidden_role = '<input type="hidden" name="role" value="3" />';
        $role_cards_disabled = true;
    }

    $output_badge_label_info = '';
    $output_badge_label_placeholder = lang('Badge Label');

    // If there is a default badge label in the site settings,
    // then output info about how field can be left blank for default.
    if (BADGE_LABEL != '') {
        $output_badge_label_info = '<div class="form-text">(' . lang('leave blank for default') . ': "' . h(BADGE_LABEL) . '")</div>';
        $output_badge_label_placeholder = h(BADGE_LABEL);
    }

    print
    pg_page_shell(
        array(
            'title'=> lang('Create User'),
            'extra classes'=>'users',
            'icon'=>'account',
            'heading'=>lang('Create User'),
            'heading_description' => lang('Create a new user account, assign privileges, and choose to email login info to User.'),
            'cancel'=>array('enable'=>'true','url'=>'view_users.php'),
            'breadcrumb' => array(
                array('label' => lang('All My Users'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_users.php'),
                array('label' => lang('Create User')),
            ),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                
                <form name="form" action="add_user.php" method="post">
                    ' . get_token_field() . '
                    ' . $output_hidden_role . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <input type="hidden" name="contact_id" value="' . h(isset($_GET['contact_id']) ? $_GET['contact_id'] : '') . '" />
                    <div class="card mb-5">
                        <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . lang('Role') . '</span>
                            <span class="small fg-secondary">' . lang('The role decides which rights the account carries by itself.') . '</span>
                        </div>
                        <div class="card-body">
                            ' . pg_user_role_cards(3, $user['role'], $role_cards_disabled) . '
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
                                    <input type="text" name="username" placeholder="' . lang('Username') . '" id="username" maxlength="100" class="form-control" required />
                                    <div class="form-text">' . lang('Used when signing in.') . '</div>
                                </div>
                                <div class="col-12 lg:col-4">
                                    <label class="form-label" for="email">' . lang('User Email') . '</label>
                                    <input value="' . h($email_address) . '" type="email" class="form-control" id="email" name="email" maxlength="100" inputmode="email" data-inputmask-alias="email" required/>
                                    <div class="form-text">' . lang('Password reset goes to this address.') . '</div>
                                </div>
                                <div class="col-12 lg:col-4">
                                    <label for="home_page" class="form-label">' . lang('User Start Page') . '</label>
                                    <select class="form-control" name="home_page" id="home_page"><option value="0">[' . lang('None') . ']</option>' . select_page() . '</select>
                                    <div class="form-text">' . lang('Opened after signing in.') . '</div>
                                </div>
                            </div>

                            <hr class="border-subtle-secondary opacity-50 my-5">
                            <div class="text-uppercase small fw-semibold fg-secondary mb-3">' . lang('Optional') . '</div>

                            <div class="row g-5">
                                <div class="col-12 sm:col-6 lg:col-4">
                                    <label class="form-label" for="reward_points">' . lang('Reward Program, Reward Points') . '</label>
                                    <input type="number" class="form-control" id="reward_points" name="reward_points" maxlength="9"/>
                                </div>
                                <div class="col-12">
                                    <div class="form-field">
                                        <div class="switch"><input type="checkbox" id="badge" name="badge" value="1" class="collapse-switcher" data-bs-target="#badge_row"/></div>
                                        <label class="" for="badge">' . lang('Show badge next to username') . '</label>
                                    </div>
                                    <div class="collapse ms-7 mt-1 mb-3" id="badge_row" style="max-width:22rem">
                                        <input type="text" name="badge_label" placeholder="' . $output_badge_label_placeholder . '" id="badge_label" class="form-control form-control-sm" value="" size="20" maxlength="100" />
                                        ' . $output_badge_label_info . '
                                    </div>
                                    <div class="form-field">
                                        <div class="switch"><input type="checkbox" id="notify_user" name="notify_user" value="1" class=""/></div>
                                        <label class="" for="notify_user">' . lang('Notify User: Send email with login info to User') . '</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    ' . pg_user_form_rights_cards($permission_ui['rows']) . '

                    ' . $permission_ui['panels'] . '

                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();

} else {

    validate_token_field();

    $_POST['username'] = trim($_POST['username']);
    $_POST['email'] = trim($_POST['email']);

    // The role arrives as text. Cast it before the ceiling check so a
    // non-numeric value cannot slip past the comparison and reach the SQL,
    // where a lenient server would store it as 0 (administrator).
    if (isset($_POST['role'])) {
        $_POST['role'] = (int) $_POST['role'];

        if (in_array($_POST['role'], array(0, 1, 2, 3), true) == false) {
            log_activity(lang('access denied because user does not have access to create a user with the requested role'), $_SESSION['sessionusername']);
            output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
    }

    // if editor is not an administrator and the editor's role is less than or equal to the role that the editor is trying to set, then output error
    if (($user['role'] != 0) && ($user['role'] >= $_POST['role'])) {
        log_activity(lang('access denied because user does not have access to create a user with the requested role'), $_SESSION['sessionusername']);
        output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // If the username is blank, then output error.
    if ($_POST['username'] == '') {
        output_error(lang(array('string'=>'{var:1} is required','vars'=>lang('Username'))) . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    // validate e-mail address
    if (validate_email_address($_POST['email']) == FALSE) {
        output_error(lang(array('string'=>'{var:1} is invalid','vars'=>lang('The email address'))) . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    // determine if username is already in use
    $result = db_q("SELECT user_id FROM user WHERE (user_username = ?) OR (user_email = ?)", array((string) ($_POST['username'] ?? ''), (string) ($_POST['username'] ?? ''))) or output_error('Query failed');
    if (mysqli_num_rows($result) > 0)
    {
        output_error(lang('The username that you entered is already in use') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }
    // determine if e-mail address is already in use
    $result = db_q("SELECT user_id FROM user WHERE (user_email = ?) OR (user_username = ?)", array((string) ($_POST['email'] ?? ''), (string) ($_POST['email'] ?? ''))) or output_error('Query failed');
    if (mysqli_num_rows($result) > 0)
    {
        output_error(lang('The email address that you entered is already in use') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    $random_password = get_random_string(array(
        'type' => 'lowercase_letters',
        'length' => 10));
    
    $sql_set_page_type_columns = '';
    $sql_set_page_type_values = '';
        
    // if forms are enabled, then set the sql to update the set page type columns that are associated with this feature
    if (FORMS == true) {
        $sql_set_page_type_columns .= 
            " user_set_page_type_custom_form,
            user_set_page_type_custom_form_confirmation,
            user_set_page_type_form_list_view,
            user_set_page_type_form_item_view,
            user_set_page_type_form_view_directory,";
        
        $sql_set_page_type_values .= 
            " '" . escape($_POST['set_page_type_custom_form'] ?? '') . "',
            '" . escape($_POST['set_page_type_custom_form_confirmation'] ?? '') . "',
            '" . escape($_POST['set_page_type_form_list_view'] ?? '') . "',
            '" . escape($_POST['set_page_type_form_item_view'] ?? '') . "',
            '" . escape($_POST['set_page_type_form_view_directory'] ?? '') . "',";
    }
    
    // if calendars are enabled, then set the sql to update the set page type columns that are associated with this feature
    if (CALENDARS == true) {
        $sql_set_page_type_columns .= 
            " user_set_page_type_calendar_view,
            user_set_page_type_calendar_event_view,";
        
        $sql_set_page_type_values .= 
            " '" . escape($_POST['set_page_type_calendar_view'] ?? '') . "',
            '" . escape($_POST['set_page_type_calendar_event_view'] ?? '') . "',";
    }
    
    // if ecommerce is enabled, then set the sql to update the set page type columns that are associated with this feature
    if (ECOMMERCE == true) {
        $sql_set_page_type_columns .= 
            " user_set_page_type_catalog,
            user_set_page_type_catalog_detail,
            user_set_page_type_express_order,
            user_set_page_type_order_form,
            user_set_page_type_shopping_cart,
            user_set_page_type_shipping_address_and_arrival,
            user_set_page_type_shipping_method,
            user_set_page_type_billing_information,
            user_set_page_type_order_preview,
            user_set_page_type_order_receipt,";
        
        $sql_set_page_type_values .= 
            " '" . escape($_POST['set_page_type_catalog'] ?? '') . "',
            '" . escape($_POST['set_page_type_catalog_detail'] ?? '') . "',
            '" . escape($_POST['set_page_type_express_order'] ?? '') . "',
            '" . escape($_POST['set_page_type_order_form'] ?? '') . "',
            '" . escape($_POST['set_page_type_shopping_cart'] ?? '') . "',
            '" . escape($_POST['set_page_type_shipping_address_and_arrival'] ?? '') . "',
            '" . escape($_POST['set_page_type_shipping_method'] ?? '') . "',
            '" . escape($_POST['set_page_type_billing_information'] ?? '') . "',
            '" . escape($_POST['set_page_type_order_preview'] ?? '') . "',
            '" . escape($_POST['set_page_type_order_receipt'] ?? '') . "',";
    }
    
    // Modern password hash plus the algo stamp; see pg_password_insert_fragments().
    $sql_password = pg_password_insert_fragments($random_password);

    // The workspace rights, when their columns exist.
    $sql_ws_columns = pg_user_permission_ws_sql('columns');
    $sql_ws_values = pg_user_permission_ws_sql('values');

    // The ERP's read-only right, when its column exists.
    $sql_erp_readonly_column = pg_user_erp_readonly_sql('columns');
    $sql_erp_readonly_value = pg_user_erp_readonly_sql('values');

    // insert row into user table
    $query =
        "INSERT INTO user (
            user_username,
            user_email,
            user_password,
            {$sql_password['algo_column']}
            user_role,
            user_home,
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
            $sql_erp_readonly_column
            $sql_ws_columns
            user_set_offline_payment,
            user_publish_calendar_events,
            user_set_page_type_email_a_friend,
            user_set_page_type_folder_view,
            user_set_page_type_photo_gallery,
            $sql_set_page_type_columns
            selected_appmenu_items_array,
            user_contact,
            user_timestamp,
            user_user)
        VALUES (
            '" . escape($_POST['username'] ?? '') . "',
            '" . escape($_POST['email'] ?? '') . "',
            " . $sql_password['password'] . ",
            {$sql_password['algo_value']}
            '" . escape($_POST['role'] ?? '') . "',
            '" . escape($_POST['home_page'] ?? '') . "',
            '" . escape($_POST['badge'] ?? '') . "',
            '" . escape($_POST['badge_label'] ?? '') . "',
            '" . escape($_POST['reward_points'] ?? '') . "',
            '" . escape($_POST['manage_contacts'] ?? '') . "',
            '" . escape($_POST['manage_visitors'] ?? '') . "',
            '" . escape($_POST['create_pages'] ?? '') . "',
            '" . escape($_POST['delete_pages'] ?? '') . "',
            '" . escape($_POST['manage_forms'] ?? '') . "',
            '" . escape($_POST['manage_calendars'] ?? '') . "',
            '" . escape($_POST['manage_emails'] ?? '') . "',
            '" . escape($_POST['manage_ecommerce'] ?? '') . "',
            '" . escape($_POST['view_card_data'] ?? '') . "',
            '" . e($_POST['manage_ecommerce_reports'] ?? '') . "',
            '" . e($_POST['manage_erp'] ?? '') . "',
            '" . e($_POST['manage_erp_cash'] ?? '') . "',
            '" . e($_POST['manage_erp_settings'] ?? '') . "',
            $sql_erp_readonly_value
            $sql_ws_values
            '" . escape($_POST['set_offline_payment'] ?? '') . "',
            '" . escape($_POST['publish_calendar_events'] ?? '') . "',
            '" . escape($_POST['set_page_type_email_a_friend'] ?? '') . "',
            '" . escape($_POST['set_page_type_folder_view'] ?? '') . "',
            '" . escape($_POST['set_page_type_photo_gallery'] ?? '') . "',
            $sql_set_page_type_values
            'default',
            '" . escape($_POST['contact_id'] ?? '') . "',
            UNIX_TIMESTAMP(),
            '$user[id]')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $user_id = mysqli_insert_id(db::$con);

    // Offers that ask for a new customer read the creation date; user_timestamp
    // is rewritten on every save and cannot answer that.
    pg_user_created_stamp($user_id);

    // A new account starts with the notification history already read. Read
    // state is per person, so without this the first sign-in opens onto every
    // announcement the site has ever made, none of it theirs to act on.
    include_once(dirname(__FILE__) . '/includes/notifications.php');
    pg_notification_seed_user($user_id);

    // A new account's password is as old as the account. Written after the
    // INSERT rather than inside it so the column list stays valid on a schema
    // that predates the 2026.4.4 step.
    if (pg_user_has_password_changed_at()) {
        db("UPDATE user SET user_password_changed_at = UNIX_TIMESTAMP() WHERE user_id = '" . e($user_id) . "'");
    }
    
    pg_user_form_save_access($user_id, $_POST['role'], $_POST, false);

    // If the user checked to notify the user, then send email to user.
    if ($_POST['notify_user'] == 1) {
        $login = '';
        
        // if user's role is administrator, designer, or manager
        // or user has edit rights
        // or user has access to control panel
        // then set link to /SOFTWARE_DIRECTORY/
        if (
            ($_POST['role'] < 3)
            || (no_acl_check($user_id) == true)
            || ($_POST['manage_calendars'] == 'yes')
            || ($_POST['manage_forms'] == 'yes')
            || ($_POST['manage_visitors'] == 'yes')
            || ($_POST['manage_contacts'] == 'yes')
            || ($_POST['manage_emails'] == 'yes')
            || ($_POST['manage_ecommerce'] == 'yes')
            || $_POST['manage_ecommerce_reports']
            || ($_POST['manage_erp'] ?? '')
            || ($_POST['manage_workspace'] ?? '')
            || (count(get_items_user_can_edit('ad_regions', $user_id)) > 0)
        ) {
            // The link leaves the request in an e-mail, so it is built from the
            // configured host name rather than the Host header of this request.
            $login = 
                lang('Login') . ':' . "\n" .
                URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/' . "\n";

        // else if there was a send to page selected for this user        
        } elseif ($_POST['home_page']) {
            $login =
                lang('Login') . ':' . "\n" .
                URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path(get_page_name($_POST['home_page'])) . "\n";
        }

        // The e-mail never carries the password: the user sets their own
        // through a single-use set password link (pg_set_password_link()).
        // The link works for whoever reads it, so the e-mail is 'secret':
        // a failed delivery keeps its body out of the activity log and the
        // delivery-failed notice.
        $set_password_url = pg_set_password_link_issue($user_id) ? pg_set_password_link($user_id) : '';

        email(array(
            'to' => $_POST['email'],
            'from_name' => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'subject' => lang('New Account'),
            'body' => pg_new_user_email_body($_POST['email'], $set_password_url, $login),
            'secret' => true));
        
    }

    log_activity(lang(array('string'=>'user ({var:1}) was created','vars'=>$_POST['username'])), $_SESSION['sessionusername']);

    pg_audit_record('create', 'user', (int) $user_id);

    // If the user was notified, then prepare password message related to that.
    if ($_POST['notify_user'] == 1) {
        $password_message = ($set_password_url !== '')
            ? lang('A link to set a password has been emailed to the user.')
            : lang('The user has been emailed and can set a password with "Forgot password" on the sign-in page.');

    // Otherwise the user was not notified, so prepare different password message for that.
    } else {
        $password_message = lang(array('string'=>'The password is \'{var:1}\'. The user has not been notified.','vars'=>h($random_password)));
    }
    
    // Output Confirmation
    $liveform_view_users = new liveform('view_users');
    $liveform_view_users->add_notice(lang('The user has been created. ') . $password_message);
    
    // If there is a send to value then send user back to that screen
    if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
        
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_users.php');
    }
    exit();
}