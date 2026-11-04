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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');
$user = validate_user();
validate_area_access($user, 'manager');
$liveform = new liveform('add_user');

// The contact, username and e-mail are one value each; one sent as an array
// (?contact_id[]=x, username[]=x) counts as not given: escape() and trim()
// refuse an array and the request ended in an error page.
pg_request_single_values(array('contact_id', 'username', 'email'));

if (!$_POST) {
    $email_address = '';
    $contact_id = (string) ($_GET['contact_id'] ?? '');

    // If the editing user came from the edit contact screen,
    // then get email address for contact in order to prefill email field.
    // A contact that is gone or already has an account is not offered: a
    // contact has one account at most.
    if ($contact_id !== '') {
        $contact_holder = pg_user_contact_holder($contact_id);
        $contact_problem = pg_user_form_contact_problem($contact_id, $contact_holder);

        if ($contact_problem !== '') {
            $liveform->add_warning(pg_user_form_contact_problem_message($contact_problem, $contact_holder['username']));
            $contact_id = '';
        } else {
            $email_address = db_value("SELECT email_address FROM contacts WHERE id = '" . escape($contact_id) . "'");
        }
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

    $output_page_shell =
    pg_page_shell(
        array(
            'title'=> lang('Create User'),
            'extra classes'=>'users',
            'icon'=>'account',
            'heading'=>lang('Create User'),
            'heading_description' => lang('Create a user account, give it a role and rights, and optionally e-mail the person a link to set a password.'),
            'cancel' => array('enable' => 'true', 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_users.php')),
            'breadcrumb' => array(
                array('label' => lang('Users'), 'url' => pg_send_to_list_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_users.php')),
                array('label' => lang('Create User')),
            ),
        )
    );

    $output_main = '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                <form name="form" action="add_user.php" method="post">
                    ' . get_token_field() . '
                    ' . $output_hidden_role . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <input type="hidden" name="contact_id" value="' . h($contact_id) . '" />
                    ' . pg_user_form_layout('
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
                                    <input type="number" min="0" max="4294967295" step="1" class="form-control" id="reward_points" name="reward_points"/>
                                </div>
                                <div class="col-12">
                                    <div class="form-field">
                                        <div class="switch"><input type="checkbox" id="badge" name="badge" value="1" class="collapse-switcher" data-bs-target="#badge_row"/></div>
                                        <label class="" for="badge">' . lang('Show badge next to username') . '</label>
                                    </div>
                                    <div class="collapse ms-7 mt-1 mb-3" id="badge_row" style="max-width:22rem">
                                        <input type="text" name="badge_label" placeholder="' . $output_badge_label_placeholder . '" id="badge_label" aria-label="' . h(lang('Badge Label')) . '" class="form-control form-control-sm" value="" size="20" maxlength="100" />
                                        ' . $output_badge_label_info . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    ' . pg_user_form_rights_cards($permission_ui['rows']), '
                    <div class="card mb-5">
                        <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . lang('Password') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="form-field">
                                <div class="switch"><input type="checkbox" id="notify_user" name="notify_user" value="1"/></div>
                                <label for="notify_user">' . lang('Notify User: e-mail a link to set a password') . '</label>
                            </div>
                        </div>
                    </div>', '
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>') . '

                    ' . $permission_ui['panels'] . '
                </form>
            </div>
        </div>
    
</main>';

    // A refused save comes back here with what was sent.
    $sent_values = pg_user_form_sent_values('add_user');

    if ($sent_values) {
        $output_main = pg_user_form_refill($output_main, $sent_values);
    }

    print $output_page_shell . $output_main . output_footer();

    $liveform->remove_form();

} else {

    validate_token_field();

    $_POST['username'] = trim($_POST['username'] ?? '');
    $_POST['email'] = trim($_POST['email'] ?? '');

    // Only a whole number from 0 to 3 is a role: text such as 'abc' would
    // cast to 0 (administrator). A form that sends no role creates a user.
    $_POST['role'] = pg_user_form_role($_POST['role'] ?? null, 3);

    if ($_POST['role'] === null) {
        log_activity(lang('access denied because user does not have access to create a user with the requested role'), $_SESSION['sessionusername']);
        output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // A role at or above the editor's own is refused, except to an administrator.
    if (!pg_user_role_writable($user['role'], $_POST['role'])) {
        log_activity(lang('access denied because user does not have access to create a user with the requested role'), $_SESSION['sessionusername']);
        output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // Points out of range and texts longer than their column, a blank
    // username, an invalid e-mail address, one either account field already
    // uses, and a contact that is gone or has an account: the form opens
    // again with the error and what was sent.
    foreach (pg_user_form_field_problems($_POST) as $field => $field_problem) {
        $liveform->mark_error($field, pg_user_form_field_problem_message($field, $field_problem));
    }

    if (!$liveform->check_form_errors()) {
        $problem = pg_user_write_problem($_POST['username'], $_POST['email']);

        if ($problem !== '') {
            $liveform->mark_error(($problem === 'email_invalid' || $problem === 'email_taken') ? 'email' : 'username',
                h(pg_user_write_problem_message($problem)) . '.');
        }
    }

    $contact_holder = array('found' => false, 'username' => null);

    if (pg_user_contact_sql($_POST['contact_id'] ?? '') !== 'NULL') {
        $contact_holder = pg_user_contact_holder($_POST['contact_id']);
    }

    $contact_problem = pg_user_form_contact_problem($_POST['contact_id'] ?? '', $contact_holder);

    if ($contact_problem !== '') {
        $liveform->mark_error('contact_id', pg_user_form_contact_problem_message($contact_problem, $contact_holder['username']));
    }

    if ($liveform->check_form_errors()) {
        $liveform->add_fields_to_session();

        // The contact link is dropped when it was the problem, so the account
        // can be created without it.
        $back = array('send_to' => (string) ($_REQUEST['send_to'] ?? ''));

        if ($contact_problem === '') {
            $back['contact_id'] = (string) ($_POST['contact_id'] ?? '');
        }

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_user.php?' . http_build_query(array_filter($back, 'strlen')));
        exit();
    }

    // A checked private folder whose date is not one stops here, before
    // anything is written; a date already gone is named after the save.
    $past_expiry_folders = pg_user_form_check_expiry_dates($_POST);

    $created = pg_user_create($_POST, $user['id']);
    $user_id = $created['id'];

    // If the user checked to notify the user, then send email to user.
    if (($_POST['notify_user'] ?? '') == 1) {
        $mail = pg_user_new_account_mail($user_id, $_POST);
        $sent = $mail['sent'];
        $set_password_url = $mail['set_password_url'];
    }

    log_activity(lang(array('string'=>'user ({var:1}) was created','vars'=>$_POST['username'])), $_SESSION['sessionusername']);

    // Output Confirmation
    $liveform_view_users = new liveform('view_users');
    // A mail that was not sent leaves an account nobody can sign in to yet,
    // so it is a warning the operator acts on, not a confirmation.
    $notify_user = (($_POST['notify_user'] ?? '') == 1);
    $new_user_notice = h(pg_new_user_notice($notify_user, $sent ?? false, $set_password_url ?? '', $created['password']));

    if ($notify_user && empty($sent)) {
        $liveform_view_users->add_warning($new_user_notice);
    } else {
        $liveform_view_users->add_notice($new_user_notice);
    }

    if ($past_expiry_folders) {
        $liveform_view_users->add_warning(pg_user_form_past_expiry_warning($past_expiry_folders));
    }
    
    // If there is a send to value then send user back to that screen
    if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
        
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_users.php');
    }
    exit();
}