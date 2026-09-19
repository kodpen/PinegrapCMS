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


ini_set('max_execution_time', '9999');
include('init.php');
include_once('liveform.class.php');
include_once('includes/user_permissions.php');
$liveform = new liveform('import_users');
$liveform_view_users = new liveform('view_users');
$user = validate_user();
validate_area_access($user, 'manager');

if (!$_POST) {
    // if user is not an administrator or designer, then prepare to disable role picklist
    if ($user['role'] <= 1) {    
        $output_role_disabled_class = '';
        $output_role_disabled_attribute = '';
    }else{
        $output_role_disabled_class =' disabled';
        $output_role_disabled_attribute = 'disabled="disabled"';
    }
    
    // Selection markup for the permission panels. Imported users all get the
    // same rights, so nothing here is per-user and every panel starts empty.
    $permission_panels = array(
        'edit_tree'      => get_acl_folder_tree('edit'),
        'page_types'     => get_page_type_checkboxes_and_labels(),
        'common_regions' => get_checkboxes_for_items_user_can_edit('common_regions'),
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
            $output_calendars .= '<div class="form-check"><input type="checkbox" name="calendar_' . $row['id'] . '" id="calendar_' . $row['id'] . '" value="1" class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="calendar_' . $row['id'] . '"> ' . h($row['name']) . '</label></div>';
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
            . pg_user_permission_switch('manage_erp_settings', '1', false, lang('Change ERP settings'), lang('Numbering, default accounts and the Parasut connection.'));
    }

    if (ADS === true) {
        $permission_panels['ad_regions'] = get_checkboxes_for_items_user_can_edit('ad_regions');
    }

    // Contact groups, used twice on this screen: the groups the imported users
    // may manage (a permission panel) and the groups they are imported into (a
    // card of their own).
    $contact_groups = db_items("SELECT id, name FROM contact_groups ORDER BY name");

    $output_contact_groups = '';
    $output_contact_groups_for_contact = '';

    if ($contact_groups) {

        foreach ($contact_groups as $contact_group) {

            $output_contact_groups .= '<div class="form-check"><input type="checkbox" name="contact_group_' . $contact_group['id'] . '" id="contact_group_' . $contact_group['id'] . '" value="1" class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="contact_group_' . $contact_group['id'] . '">' . h($contact_group['name']) . '</label></div>';

            $output_contact_groups_for_contact .= '
            <div class="col-12 col-md-4 my-1">
                <div class="form-check form-switch">
                    <input type="checkbox" name="contact_group_for_contact_' . $contact_group['id'] . '" id="contact_group_for_contact_' . $contact_group['id'] . '" value="1" class="form-check-input"/>
                    <label class="form-check-label" for="contact_group_for_contact_' . $contact_group['id'] . '"> ' . h($contact_group['name']) . '</label>
                </div>
            </div>';
        }
    }

    $permission_panels['contact_groups'] = $output_contact_groups;

    $permission_ui = pg_user_permission_ui(array(
        'values' => array('create_pages' => '1', 'delete_pages' => '1', 'publish_calendar_events' => 'yes'),
        'panels' => $permission_panels,
        'counts' => array(),
    ));

    $output_hidden_role  = '';
    $role_cards_disabled = false;

    // A manager may only import users, so the picker is read-only and the role
    // travels in a hidden field - a disabled control posts nothing.
    if ($user['role'] == 2) {
        $output_hidden_role  = '<input type="hidden" name="role" value="3" />';
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

    echo
    pg_page_shell(
        array(
            'title'=> lang('Import Users'),
            'extra classes'=>'users',
            'icon'=>'account', 
            'heading'=>lang('Import Users'),
            'heading_description' => lang('Import new Users and Contacts, assign privileges, and choose to email login info to Users.'),
            'cancel'=>array('enable'=>'true','url'=>'view_users.php'),
        
            'breadcrumb' => array(array('label' => lang('All My Users'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_users.php'), array('label' => lang('Import Users'))),
        )
    ) . '
<main id="content" class="container-fluid">
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" enctype="multipart/form-data" action="import_users.php" method="post">
                    ' . get_token_field() . '
                    ' . $output_hidden_role . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="card mb-3">
                        <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Import CSV') . '</span>
                        </div>
                        <div class="card-body">
                            <label for="file" class="form-label">' . lang('Select Formatted Text File to Upload') . '</label>
                            ' . $liveform->output_field(array('type'=>'file', 'name'=>'file', 'size'=>'60', 'id'=>'file', 'class'=>'form-control w-auto')) . '
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Role') . '</span>
                            <span class="small text-body-secondary">' . lang('Every imported user gets this role and these rights.') . '</span>
                        </div>
                        <div class="card-body">
                            ' . pg_user_role_cards(3, $user['role'], $role_cards_disabled) . '
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Account') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-lg-5">
                                    <label for="home_page" class="form-label">' . lang('User Start Page') . '</label>
                                    <select class="form-select" name="home_page" id="home_page"><option value="0">[' . lang('None') . ']</option>' . select_page() . '</select>
                                    <div class="form-text">' . lang('Opened after signing in.') . '</div>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-4">
                                    <label class="form-label" for="reward_points">' . lang('Reward Program, Reward Points') . '</label>
                                    <input type="number" class="form-control" id="reward_points" name="reward_points" maxlength="9"/>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" id="badge" name="badge" value="1" class="form-check-input collapse-switcher" data-bs-target="#badge_row"/>
                                        <label class="form-check-label" for="badge">' . lang('Show badge next to username') . '</label>
                                    </div>
                                    <div class="collapse ms-4 mt-1 mb-2" id="badge_row" style="max-width:22rem">
                                        <input type="text" name="badge_label" placeholder="' . $output_badge_label_placeholder . '" id="badge_label" class="form-control form-control-sm" value="" size="20" maxlength="100" />
                                        ' . $output_badge_label_info . '
                                    </div>
                                    <div class="form-check form-switch">
                                        <input type="checkbox" id="notify_user" name="notify_user" value="1" class="form-check-input"/>
                                        <label class="form-check-label" for="notify_user">' . lang('Notify User: Send email with login info to User') . '</label>
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

                    <div class="card mb-3">
                        <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Select Contact Groups to Import into') . '</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                ' . $output_contact_groups_for_contact . '
                            </div>
                        </div>
                    </div>

                    ' . $permission_ui['panels'] . '

                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" name="submit_import" value="Import" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Importing') ) . '" ><span class="material-icons me-2">file_upload</span><span class="btn-text" >' . lang(array('string'=>'Import Users') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
    
    $liveform->remove_form('import_users');

} else {
    validate_token_field();
    
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
    
    // if no file was uploaded
    if (empty($_FILES['file']['name'])) {
        $liveform->mark_error('file', lang('Please select a file.'));
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/import_users.php?send_to=' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : ''));
        exit();
    }

    // Fix classic Mac (CR-only) line endings: fgetcsv() splits only on LF unless this
    // setting is on. It is deprecated since PHP 8.1 but still functional through 8.5,
    // so the E_DEPRECATED notice is suppressed instead of dropping CR support.
    @ini_set('auto_detect_line_endings', true);
    
    // get file handle for uploaded CSV file
    $handle = fopen($_FILES['file']['tmp_name'], "r");
    // get column names from first row of CSV file
    $columns = fgetcsv($handle, 100000, ",");
    
    // if file is empty
    if (!$columns) {
        $liveform->mark_error('file', lang('The file was empty.'));
        fclose($handle);
        
        // Redirect user back to the import_users page
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/import_users.php?send_to=' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : ''));
        exit();
    }
    
    // create array with column field names
    $column_names = array();
    foreach ($columns as $key => $value) {
        $column_names[] = convert_column_name($value);
    }

    // The email_address and username columns are optional; their key stays null
    // when the CSV does not contain them.
    $email_address_key = null;
    $username_key = null;
    
    // foreach column field name
    foreach ($column_names as $key => $value) {
        // if the column is invalid, remove from column list
        if ($value === FALSE) {
            unset($column_names[$key]);
        }
        
        // if column is email_address, then store key location for email_address
        if ($value == 'email_address') {
            $email_address_key = $key;
        }
        
        // if column is username, then store key location for username
        if ($value == 'username') {
            $username_key = $key;
        }
    }
    
    // Return a CSV cell as a string, or '' when the column is missing from the file or the row.
    $import_cell = function ($row, $key) {
        return (($key !== null) && isset($row[$key])) ? (string) $row[$key] : '';
    };

    // Setup variables
    $users_to_be_imported = array();
    $invalid_emails_count = 0;
    $invalid_email_list = '';
    $pre_existing_emails_count = 0;
    $pre_existing_email_list = '';
    $pre_existing_users_count = 0;
    $pre_existing_user_list = '';
    $import_user_error = false;

    // loops through all rows of data in CSV file
    while ($row = fgetcsv($handle, 100000, ",")) {
        $current_import_user_error = false;

        // If there is an email address in this row, then continue.
        if (trim($import_cell($row, $email_address_key)) != '') {
            // Validate email
            if (validate_email_address($import_cell($row, $email_address_key)) == FALSE) {
                $invalid_emails_count ++;
                if ($invalid_email_list) {
                    $invalid_email_list .= ', ';
                }
                $invalid_email_list .= $import_cell($row, $email_address_key);
                $current_import_user_error = true;
                $import_user_error = true;
            }

            // If there is a username in this row, then check if the username
            // is already in use.
            if (trim($import_cell($row, $username_key)) != '') {
                // Check if the username is already in use
                $result = mysqli_query(db::$con, "SELECT user_id FROM user WHERE (user_username = '" . escape($import_cell($row, $username_key)) . "') OR (user_email = '" . escape($import_cell($row, $username_key)) . "')") or output_error('Query failed');
                if (mysqli_num_rows($result) > 0)
                {
                    $pre_existing_users_count ++;
                    if ($pre_existing_user_list) {
                        $pre_existing_user_list .= ', ';
                    }
                    $pre_existing_user_list .= $import_cell($row, $username_key);
                    $current_import_user_error = true;
                    $import_user_error = true;
                }
            }

            // Check if the email_address is already in use
            $result = mysqli_query(db::$con, "SELECT user_id FROM user WHERE (user_email = '" . escape($import_cell($row, $email_address_key)) . "') OR (user_username = '" . escape($import_cell($row, $email_address_key)) . "')") or output_error('Query failed');
            if (mysqli_num_rows($result) > 0)
            {
                $pre_existing_emails_count ++;
                if ($pre_existing_email_list) {
                    $pre_existing_email_list .= ', ';
                }
                $pre_existing_email_list .= $import_cell($row, $email_address_key);
                $current_import_user_error = true;
                $import_user_error = true;
            }

            if ($current_import_user_error === false) {
                // If there are no errors, then prepare to add user.
                $users_to_be_imported[] = $row;
            }

        // Otherwise there is not an email address in this row, so output error.
        } else {
            // There was an improperly formatted row in the csv file so tell the user to fix it.
            $liveform->mark_error('file', lang('There are errors in your .csv file. Please check each users format and then try again.'));
            
            // Forward them back to the import users screen.
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/import_users.php?send_to=' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : ''));
            exit();
        }
        
    }

    fclose($handle);
 
    // If there is an error, mark it and redirect the user
    if ($import_user_error) {
        $combined_errors = '';
        
        // If there were emails that had an invalid format
        if ($invalid_emails_count > 0) {
            $combined_errors .= '<p>' . lang(array('string'=>'{var:1} email address(es) were invalid. ({var:2})','vars'=>array($invalid_emails_count,$invalid_email_list))) . '</p>';
        }
        
        // If there were users that already exist
        if ($pre_existing_users_count > 0) {
            $combined_errors .= '<p>' . lang(array('string'=>'{var:1} user name(s) already exist. ({var:2})','vars'=>array($pre_existing_users_count,$pre_existing_user_list))) . '</p>';
        }
        
        // If there were email addresses that already exist.
        if ($pre_existing_emails_count > 0) {
            $combined_errors .= '<p>' . lang(array('string'=>'{var:1} email address(es) already exist. ({var:2})','vars'=>array($pre_existing_emails_count,$pre_existing_email_list))) . '</p>';
        }
        
      
        
        // Mark the errors.
        $liveform->mark_error('general_error', '<h4 class="alert-heading">' . lang('The file you selected could not be imported because of the following error(s)') . ':</h4>' . $combined_errors);
        
        // Forward them back to the import users screen.
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/import_users.php?send_to=' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : ''));
        exit();
    }
    
    $contact_column_list = '';

    // Build list of contact column names for database query.
    foreach ($column_names as $key => $value) {
        // If this is not the username column, then add it.
        if ($value != 'username') {
            $contact_column_list .= "$value, ";
        }
    }

    $contact_column_list .= 'user, timestamp';

    $contact_groups = db_items("SELECT id FROM contact_groups");

    $contact_groups_for_contact = array();

    foreach ($contact_groups as $contact_group) {
        // If contact group was checked, add contact group to array.
        if (($_POST['contact_group_for_contact_' . $contact_group['id']] ?? '') == 1) {
            $contact_groups_for_contact[] = $contact_group;
        }
    }

    $imported_users = 0;
    
    // Loop through the valid users and import them
    foreach ($users_to_be_imported as $user_to_be_imported) {

        $username = trim($import_cell($user_to_be_imported, $username_key));
        $email_address = trim($import_cell($user_to_be_imported, $email_address_key));

        // If the username in the CSV file was blank,
        // then create a username by using everything before "@" in the email address,
        // and, if necessary, add numbers to the end to make it unique.
        if ($username == '') {
            $username = strtok($email_address, '@');
            $username = get_unique_username($username);
        }

        // If a user in the db already has the same username or email,
        // then don't add this user, and skip to the next one.
        if (
            db_value(
                "SELECT user_id
                FROM user
                WHERE
                    (user_username = '" . e($username) . "')
                    OR (user_username = '" . e($email_address) . "')
                    OR (user_email = '" . e($username) . "')
                    OR (user_email = '" . e($email_address) . "')")
        ) {
            continue;
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
                user_set_offline_payment,
                user_publish_calendar_events,
                user_set_page_type_email_a_friend,
                user_set_page_type_folder_view,
                user_set_page_type_photo_gallery,
                $sql_set_page_type_columns
                user_timestamp,
                user_user)
            VALUES (
                '" . escape($username) . "',
                '" . escape($email_address) . "',
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
                '" . escape($_POST['set_offline_payment'] ?? '') . "',
                '" . escape($_POST['publish_calendar_events'] ?? '') . "',
                '" . escape($_POST['set_page_type_email_a_friend'] ?? '') . "',
                '" . escape($_POST['set_page_type_folder_view'] ?? '') . "',
                '" . escape($_POST['set_page_type_photo_gallery'] ?? '') . "',
                $sql_set_page_type_values
                UNIX_TIMESTAMP(),
                '$user[id]')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $user_id = mysqli_insert_id(db::$con);

        // The same post-insert stamps a user created on the add-user screen
        // gets: the creation date offers read, the notification history marked
        // read so the first sign-in does not open onto every past announcement,
        // and the password age (written after the INSERT so the column list
        // stays valid on a schema that predates the 2026.4.4 step).
        pg_user_created_stamp($user_id);

        include_once(dirname(__FILE__) . '/includes/notifications.php');
        pg_notification_seed_user($user_id);

        if (pg_user_has_password_changed_at()) {
            db("UPDATE user SET user_password_changed_at = UNIX_TIMESTAMP() WHERE user_id = '" . e($user_id) . "'");
        }

        // insert data into aclfolder table
        $result = mysqli_query(db::$con, "SELECT folder_id FROM folder") or output_error('Query failed');
        while($row = mysqli_fetch_array($result))
        {
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
            
            $result2 = mysqli_query(db::$con, "INSERT INTO aclfolder (aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date) VALUES ('$user_id', '$folder_id', '$rights', '" . escape($sql_expiration_date) . "')") or output_error('Query failed');
        }

        // if user that was created has a user role, then prepare to assign access to various items for user
        if ($_POST['role'] == 3) {
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
                            '" . escape($user_id) . "',
                            '" . escape($common_region['cregion_id']) . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
            
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
                            '" . escape($user_id) . "',
                            '" . escape($menu['id']) . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
            
            // if manage contacts or manage e-mails was checked, check to see which contact groups the user needs to be given access to
            if ((($_POST['manage_contacts'] ?? '') == 'yes') || (($_POST['manage_emails'] ?? '') == 'yes')) {
                // loop through all contact groups
                foreach ($contact_groups as $contact_group) {
                    // if contact group was selected for user to be given access to, give access to user to contact group
                    if (($_POST['contact_group_' . $contact_group['id']] ?? '') == 1) {
                        $query =
                            "INSERT INTO users_contact_groups_xref (
                                user_id,
                                contact_group_id)
                            VALUES (
                                '" . $user_id . "',
                                '" . $contact_group['id'] . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
            }
            
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
                                '" . $user_id . "',
                                '" . $calendar['id'] . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
            }

            // If ads are enabled, then store access.
            if (ADS === true) {
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
                                '" . escape($user_id) . "',
                                '" . escape($ad_region['id']) . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
            }
        }

        $contact_data_list = '';
        
        // Loop through columns in order to prepare contact data list.
        foreach ($column_names as $key => $value) {
            // If this is not the username column, then add data for it.
            if ($value != 'username') {
                $value = escape(trim($user_to_be_imported[$key]));
                $contact_data_list .= "'$value', ";
            }
        }

        $contact_data_list .= "'$user[id]', UNIX_TIMESTAMP()";

        // Create contact.
        db("INSERT INTO contacts ($contact_column_list) VALUES ($contact_data_list)");

        $contact_id = mysqli_insert_id(db::$con);
        
        // If there is an existing contact with this email address
        // that is currently opted-out, then opt-out the new contact also.
        if (db_value("SELECT id FROM contacts WHERE (opt_in = 0) AND (email_address = '" . escape($email_address) . "') AND (id != '$contact_id')")) {
            db("UPDATE contacts SET opt_in = 0 WHERE id = '$contact_id'");
        }

        // Connect user to contact.
        db(
            "UPDATE user
            SET user_contact = '" . $contact_id . "'
            WHERE user_id = '" . $user_id . "'");

        // Loop through all checked contact groups for contact
        // in order to add this contact to those groups.
        foreach ($contact_groups_for_contact as $contact_group) {
            db(
                "INSERT INTO contacts_contact_groups_xref (
                    contact_id,
                    contact_group_id)
                VALUES (
                    '" . $contact_id . "',
                    '" . $contact_group['id'] . "')");
        }

        // If the user checked to notify the user, then send email to user.
        if (($_POST['notify_user'] ?? '') == 1) {
            $login = '';
            
            // if user's role is administrator, designer, or manager
            // or user has edit rights
            // or user has access to control panel
            // then set link to PATH/SOFTWARE_DIRECTORY/
            if (
                ($_POST['role'] < 3)
                || (no_acl_check($user_id) == true)
                || (($_POST['manage_calendars'] ?? '') == 'yes')
                || (($_POST['manage_forms'] ?? '') == 'yes')
                || (($_POST['manage_visitors'] ?? '') == 'yes')
                || (($_POST['manage_contacts'] ?? '') == 'yes')
                || (($_POST['manage_emails'] ?? '') == 'yes')
                || (($_POST['manage_ecommerce'] ?? '') == 'yes')
                || ($_POST['manage_ecommerce_reports'] ?? '')
                || ($_POST['manage_erp'] ?? '')
                || (count(get_items_user_can_edit('ad_regions', $user_id)) > 0)
            ) {
                $login = 
                    lang('Login') . ':' . "\n" .
                    URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . "\n";

            // else if there was a send to page selected for this user        
            } elseif ($_POST['home_page']) {
                $login =
                    lang('Login') . ':' . "\n" .
                    URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . encode_url_path(get_page_name($_POST['home_page'])) . "\n";
            }
            
            // e-mail user random password
            email(array(
                'to' => $email_address,
                'from_name' => ORGANIZATION_NAME,
                'from_email_address' => EMAIL_ADDRESS,
                'subject' => lang('New Account'),
                'body' =>
lang('An administrator has created a new account for you.  You can find your login info below.') . "

" . lang('Email') . ": $email_address
" . lang('Password') . ": $random_password
                
$login"));

        }
        
        $imported_users ++;
    }
    
    log_activity(lang(array('string'=>'{var:1} users were imported','vars'=>$imported_users)), $_SESSION['sessionusername']);
    
    // Output Confirmation
    $liveform_view_users->add_notice(lang(array('string'=>'{var:1} users were imported','vars'=>$imported_users)));
    
    // If there is a send to value then send user back to that screen
    if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
        
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_users.php');
    }
    exit();
}

function convert_column_name($column_name)
{
    // convert column name to lowercase
    $column_name = mb_strtolower($column_name);
    // remove spaces from column name
    $column_name = str_replace(' ', '', $column_name);
    // remove underscores from column name
    $column_name = str_replace('_', '', $column_name);
    // remove dashes from column name
    $column_name = str_replace('-', '', $column_name);

    switch ($column_name) {
        case 'emailaddress':
        case 'email':
            return('email_address');
            break;

        case 'username':
        case 'user':
            return('username');
            break;

        case 'salutation':
            return('salutation');
            break;

        case 'firstname':
        case 'first':
        case 'name':
            return('first_name');
            break;

        case 'lastname':
        case 'last':
            return('last_name');
            break;

        case 'suffix':
            return('suffix');
            break;

        case 'nickname':
        case 'alias':
            return('nickname');
            break;

        case 'company':
        case 'organization':
            return('company');
            break;

        case 'title':
        case 'jobtitle':
        case 'position':
            return('title');
            break;

        case 'department':
            return('department');
            break;

        case 'officelocation':
        case 'office':
        case 'location':
            return('office_location');
            break;

        case 'businessaddress1':
        case 'businessaddress':
        case 'businessstreet1':
        case 'businessstreet':
        case 'address1':
        case 'address':
        case 'street':
            return('business_address_1');
            break;

        case 'businessaddress2':
        case 'businessstreet2':
        case 'address2':
        case 'street2':
            return('business_address_2');
            break;

        case 'businesscity':
        case 'city':
            return('business_city');
            break;

        case 'businessstate':
        case 'state':
            return('business_state');
            break;

        case 'businesscountry':
        case 'businesscountry/region':
        case 'country':
            return('business_country');
            break;

        case 'businesszipcode':
        case 'businesspostalcode':
        case 'zipcode':
        case 'zip':
        case 'postalcode':
        case 'postal':
            return('business_zip_code');
            break;

        case 'businessphone':
        case 'businessphonenumber':
        case 'phone':
        case 'phonenumber':
            return('business_phone');
            break;

        case 'businessfax':
        case 'businessfaxnumber':
        case 'fax':
        case 'faxnumber':
            return('business_fax');
            break;

        case 'homeaddress1':
        case 'homeaddress':
        case 'homestreet1':
        case 'homestreet':
            return('home_address_1');
            break;

        case 'homeaddress2':
        case 'homestreet2':
            return('home_address_2');
            break;

        case 'homecity':
            return('home_city');
            break;

        case 'homestate':
            return('home_state');
            break;

        case 'homecountry':
        case 'homecountry/region':
            return('home_country');
            break;

        case 'homezipcode':
        case 'homezip':
        case 'homepostalcode':
        case 'homepostal':
            return('home_zip_code');
            break;

        case 'homephone':
        case 'homephonenumber':
            return('home_phone');
            break;

        case 'homefax':
        case 'homefaxnumber':
            return('home_fax');
            break;

        case 'mobilephone':
        case 'mobilephonenumber':
        case 'mobile':
        case 'cellphone':
        case 'cellphonenumber':
        case 'cell':
            return('mobile_phone');
            break;

        case 'website':
        case 'web':
        case 'site':
        case 'webpage':
        case 'url':
        case 'businesswebpage':
            return('website');
            break;

        case 'leadsource':
        case 'source':
            return('lead_source');
            break;

        case 'optin':
            return('opt_in');
            break;

        case 'description':
        case 'notes':
        case 'note':
        case 'comments':
        case 'comment':
            return('description');
            break;

        case 'memberid':
            return('member_id');
            break;

        case 'expirationdate':
            return('expiration_date');
            break;
            
        case 'affiliateapproved':
            return('affiliate_approved');
            break;
            
        case 'affiliatename':
            return('affiliate_name');
            break;

        case 'affiliatecode':
            return('affiliate_code');
            break;
            
        case 'affiliatecommissionrate':
            return('affiliate_commission_rate');
            break;
    }

    return FALSE;
}