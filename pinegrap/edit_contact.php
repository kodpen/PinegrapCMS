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
$user = validate_user();
validate_contacts_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM contacts WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');

// if user does not have access to contact, then output error
if (validate_contact_access($user, $_REQUEST['id']) == false) {
    log_activity(lang('access denied to edit contact because user does not have access to a contact group that the contact is in'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

if (!$_POST) {
    $output_orders_button = '';
    $output_bar_links = '';

    // if ecommerce is on, then output orders button to show orders for contact
    if ((ECOMMERCE === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
        $output_bar_links .= '
                    <a class="btn btn-link pg-link-secondary py-0 mb-3 " href="view_orders_for_contact.php?id=' . h(urlencode($_REQUEST['id'])) . '"><span class="material-icons me-1">storefront</span>' . lang('View Orders') . '</a>';
    }

    // The ledger account behind this contact, for a user the ERP module lets
    // in: its balance and a way to it, or a button to open one filled in from
    // this card. Read from the account side; contacts.erp_account_id is only
    // a mirror.
    if (defined('ERP_ENABLED') && ERP_ENABLED && (($user['role'] < 3) || !empty($user['manage_erp']))) {
        $erp_account = db_item("SELECT id, balance FROM erp_accounts WHERE contact_id = '" . (int) $_REQUEST['id'] . "' ORDER BY id ASC LIMIT 1");

        if (is_array($erp_account)) {
            require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

            $erp_balance = (int) $erp_account['balance'];
            $erp_side = ($erp_balance > 0) ? lang('owes you') : (($erp_balance < 0) ? lang('you owe') : '');

            $output_bar_links .= '
                    <a class="btn btn-link pg-link-secondary py-0 mb-3 " href="edit_erp_account.php?id=' . (int) $erp_account['id'] . '"><i class="bi bi-journal-text me-1"></i>' . lang('Ledger account') . ': <b class="' . (($erp_balance > 0) ? 'fg-success' : (($erp_balance < 0) ? 'fg-danger' : '')) . '">' . h(erp_money_out(abs($erp_balance))) . '</b>' . (($erp_side !== '') ? ' <span class="fg-secondary">' . h($erp_side) . '</span>' : '') . '</a>';
        } elseif (($user['role'] < 3) || empty($user['manage_erp_readonly'])) {
            // The ERP's read-only right opens no account.
            $output_bar_links .= '
                    <a class="btn btn-link pg-link-secondary py-0 mb-3 " href="add_erp_account.php?contact_id=' . (int) $_REQUEST['id'] . '"><i class="bi bi-journal-plus me-1"></i>' . lang('Open an Account') . '</a>';
        }
    }

    // Everything the site keeps about this contact, and its erasure, for a
    // KVKK / GDPR request (privacy_export.php and privacy_erase.php are open
    // to staff only).
    if ($user['role'] < 3) {
        $output_bar_links .= '
                    <a class="btn btn-link pg-link-secondary py-0 mb-3 " href="privacy_export.php?contact_id=' . (int) $id . '"><i class="bi bi-person-down me-1" aria-hidden="true"></i>' . lang('Export personal data') . '</a>
                    <a class="btn btn-link pg-link-secondary py-0 mb-3 " href="privacy_erase.php?contact_id=' . (int) $id . '"><i class="bi bi-person-x me-1" aria-hidden="true"></i>' . lang('Erase personal data') . '</a>';
    }

    // Where the contact was talked about in the workspace, its customer
    // channels and the tasks about it.
    if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
        $workspace_contact = db_item("SELECT first_name, last_name, company FROM contacts WHERE id = '" . (int) $_REQUEST['id'] . "'");
        $workspace_label = is_array($workspace_contact)
            ? trim(trim($workspace_contact['first_name'] . ' ' . $workspace_contact['last_name']) ?: (string) $workspace_contact['company'])
            : '';
        $output_bar_links .= ws_record_button($user, 'contact', (int) $_REQUEST['id'], $workspace_label, 'btn btn-link pg-link-secondary py-0 mb-3');
    }

    if ($output_bar_links !== '') {
        $output_orders_button = '
            <nav id="button_bar" class="navigation " aria-label="Button Bar">
                <div class=" btn-group btn-group-sm flex-wrap">' . $output_bar_links . '
                </div>
            </nav>';
    }

    $query =
        "SELECT
            contacts.image,
            contacts.file_id,
            contacts.salutation,
            contacts.first_name,
            contacts.last_name,
            contacts.suffix,
            contacts.nickname,
            contacts.company,
            contacts.title,
            contacts.department,
            contacts.office_location,
            contacts.business_address_1,
            contacts.business_address_2,
            contacts.business_city,
            contacts.business_state,
            contacts.business_country,
            contacts.business_zip_code,
            contacts.business_phone,
            contacts.business_fax,
            contacts.home_address_1,
            contacts.home_address_2,
            contacts.home_city,
            contacts.home_state,
            contacts.home_country,
            contacts.home_zip_code,
            contacts.home_phone,
            contacts.home_fax,
            contacts.mobile_phone,
            contacts.email_address,
            contacts.website,
            contacts.lead_source,
            contacts.opt_in,
            contacts.description,
            contacts.member_id,
            contacts.expiration_date,
            contacts.affiliate_approved,
            contacts.affiliate_name,
            contacts.affiliate_code,
            contacts.affiliate_commission_rate,
            contacts.tax_number,
            contacts.tax_office,
            contacts.parasut_contact_id,
            user.user_id,
            user.user_username AS username,
            user.user_role
        FROM contacts
        LEFT JOIN user ON contacts.id = user.user_contact
        WHERE contacts.id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed');
    $row = mysqli_fetch_assoc($result);
    $image = '';
    if($row['file_id'] == 0){
        if($row['image']){
            $image = '
            <div class="item col">
                <div class="card bg-transparent border-0 shadow-none cursor-pointer image">
                    <div class="card-header d-flex justify-content-end p-1 border-0 bg-transparent"><button type="button" class="btn btn-link pg-link-danger bi bi-x-lg p-0" title="' . h(lang('Remove')) . '" aria-label="' . h(lang('Remove')) . '" onclick=" $(this).closest(\'.item\').remove();"></button></div>
                    <div class="card-body overflow-hidden position-relative rounded-4 w-100 p-0" style="aspect-ratio: 5 / 4;background: radial-gradient(transparent, #00000024);" title="' . h($row['image']) . '">
                        <input type="hidden" name="image_url" id="image_url" value="' . e($row['image']) . '">
                        <img class="lazy object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . h($row['image']) . '" />
                    </div>
                </div>
            </div>';
        }else{
            $image = '
            <div class="item col">
                <div class="card bg-transparent border-0 shadow-none cursor-pointer image">
                    <div class="card-header d-flex justify-content-end p-1 border-0 bg-transparent"><button type="button" class="btn btn-link pg-link-danger bi bi-x-lg p-0 opacity-0"></button></div>
                    <div class="card-body overflow-hidden position-relative rounded-4 w-100 p-0" style="aspect-ratio: 5 / 4;background: radial-gradient(transparent, #00000024);" title="' . h($row['image']) . '">
                        <div class="d-flex align-items-center justify-content-center w-100 h-100">' . pg_avatar_markup_for($row['first_name'] ?? '', $row['last_name'] ?? '', $row['company'] ?? '', $id, 'avatar-xl') . '</div>
                    </div>
                </div>
            </div>';
        }
       
    }else{
        $query = 
        "SELECT 
            files.name
        FROM files 
        WHERE files.id = ?";
        $result = db_q($query, array((string) $row['file_id'])) or output_error('Query failed.');
        $file = mysqli_fetch_array($result);
        $file_name = $file['name'];

        $image = '
        <div class="item col">
            <div class="card bg-transparent border-0 shadow-none cursor-pointer image">
                <div class="card-header d-flex justify-content-end p-1 border-0 bg-transparent"><button type="button" class="btn btn-link pg-link-danger bi bi-x-lg p-0" title="' . h(lang('Remove')) . '" aria-label="' . h(lang('Remove')) . '" onclick=" $(this).closest(\'.item\').remove();"></button></div>
                <div class="card-body overflow-hidden position-relative rounded-4 w-100 p-0" style="aspect-ratio: 5 / 4;background: radial-gradient(transparent, #00000024);" title="' . h($file_name) . '">
                    <input type="hidden" name="file_id" id="file_id" value="' . $row['file_id'] . '"/>
                    <img class="lazy object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . h($file_name) . '" />
                </div>
            </div>
        </div>';


    }
    $parasut_contact_id = h($row['parasut_contact_id'] ?? '');
    $user_id = $row['user_id'];
    $username = $row['username'];
    $user_role = $row['user_role'];

    // If this contact has a user connected to it, then output username.
    if ($username != '') {
        // If the editor user is an administrator or the editor user has access to edit this user,
        // then prepare username with link.
        if ((USER_ROLE == 0) || (USER_ROLE < $user_role)) {
            $output_user_info = '
            <div class="col-12 my-3">
                <a class="btn btn-link pg-link-secondary py-0 mb-3  users-color" href="edit_user.php?id=' . $user_id . '"><span class="material-icons me-3">account_circle</span>' . h($username) . '</a>
            </div>';

        // Otherwise the editor user does not have access to edit this user,
        // so prepare username without link.
        } else {
            $output_user_info ='
            <div class="col-12 my-3">
                <span class="py-3 mb-3 users-color" href="edit_user.php?id=' . $user_id . '"><span class="material-icons me-3">account_circle</span>' . h($username) . '</span>
            </div>';
        }

    // Otherwise this contact does not have a user connected to it, so output different content.
    } else {
        // If the user editing this contact has a manager role or above,
        // then add button to allow user to create a user for this contact.
        $output_create_user_button = '';
        if (USER_ROLE < 3) {
            $output_create_user_button = lang(array('string'=>' {var:1} connected to this contact.','vars'=>array('<a class="alert-link" href="#!" onclick="window.location=\'' . h(escape_javascript(PATH . SOFTWARE_DIRECTORY . '/add_user.php?contact_id=' . $_GET['id'])) . '\'">' . lang('Create an User') . '</a>') ));
        }

        $output_user_info ='
        <div class="col-12 my-3">
            <div class="alert theme-primary">
                <p class="mb-0">' . lang('This Contact does not currently have a User connected to it.') . $output_create_user_button . '</p>
            </div>
        </div>';
    }

    // The form shows the expiration date in the site's date format, a
    // lifetime membership blank.
    if ($row['expiration_date'] == '0000-00-00') {
        $row['expiration_date'] = '';
    }

    $row['expiration_date'] = prepare_form_data_for_output($row['expiration_date'], 'date');

    $contact_groups = pg_contact_form_groups($user);

    // The Parasut contact id: shown read-only-ish once the first invoice set
    // it, else a note; posted back unchanged either way.
    $output_parasut = (ENABLE_PARASUT ? ($parasut_contact_id ? '
                                        <div class="col-12 md:col-4 my-3">
                                            <label class="form-label fg-secondary small">' . lang('Parasut Contact ID') . '</label>
                                            <div class="input-group">
                                                <input value="' . $parasut_contact_id . '" type="text" name="parasut_contact_id" class="form-control form-control-sm font-monospace fg-secondary" />
                                                <span class="input-group-text small fg-secondary" title="' . lang('Set automatically when the first invoice is created.') . '"><i class="bi bi-link-45deg"></i></span>
                                            </div>
                                        </div>' : '
                                        <div class="col-12 md:col-4 my-3">
                                            <input type="hidden" name="parasut_contact_id" value="" />
                                            <div class="form-text fg-secondary"><i class="bi bi-info-circle me-1"></i>' . lang('Parasut Contact ID will be set automatically when the first invoice is created.') . '</div>
                                        </div>') : '<input type="hidden" name="parasut_contact_id" value="' . $parasut_contact_id . '" />');

    $email_address = h($row['email_address']);

    print
    pg_page_shell(
        array(
            'title'=> lang('Edit Contact') . ' : ' . h($row['first_name'])  . ' ' . h($row['last_name']),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang('Edit Contact'),
            'heading_description' => lang('View or update this contact\'s information, subscriber status, member status, affiliate status, or contact groups.'),
            'cancel'=>array('enable'=>'true','url'=>'view_contacts.php'),
        
            'breadcrumb' => array(array('label' => lang('All My Contacts'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php'), array('label' => lang('Edit Contact'))),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">

                        ' . $output_orders_button . '
                    </div>
                </div>
                <form name="form" action="edit_contact.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    ' . pg_contact_form_name_row($row, false, $image) . '

                    <div class="row">
                        <div class="col-12 md:col-6">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Contact\'s Subscriber Information') . '
                                </div>
                                <div class="card-body" >
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <label for="email_address" class="form-label">' . lang('Email') . '</label>
                                            <div class="input-group">
                                                <input value="' . $email_address . '" type="text" name="email_address" id="email_address" autocomplete="off" class="form-control text-end" maxlength="100" inputmode="email" data-inputmask-alias="email" />
                                                <a class="btn btn-solid theme-primary" href="mailto:' . $email_address . '" ><span class="material-icons">email</span></a>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="opt_in" name="opt_in" value="1"' . ($row['opt_in'] ? ' checked="checked"' : '') . '></div>
                                                <label class="" for="opt_in">' . lang('Opt-In Status') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 md:col-6">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Connected Account of this Contact') . '
                                </div>
                                <div class="card-body" >
                                    <div class="row">
                                        ' . $output_user_info . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    ' . pg_contact_form_detail_cards($row, false, $output_parasut, pg_contact_form_group_cells($contact_groups, pg_contact_form_group_states($_GET['id'], $contact_groups))) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('contact')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                </form>
            </div>
        </div>
    
</main>' .
        output_footer();

} else {
    validate_token_field();
    
    // if contact was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        $audit_before = pg_audit_fetch('contacts', (int) ($_POST['id'] ?? 0));

        // delete contact
        $query =
            "DELETE FROM contacts
            WHERE id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        pg_audit_record('delete', 'contacts', (int) ($_POST['id'] ?? 0), $audit_before);
        
        // delete contact references in contacts_contact_groups_xref
        $query = "DELETE FROM contacts_contact_groups_xref WHERE contact_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        
        // delete contact references in opt_in
        $query = "DELETE FROM opt_in WHERE contact_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        log_activity("contact ($_POST[first_name] $_POST[last_name]) was deleted", $_SESSION['sessionusername']);
        
        $liveform_view_contacts = new liveform('view_contacts');
        $liveform_view_contacts->add_notice(lang('The contact has been deleted.'));
        
    // else contact was not selected for delete
    } else {
        $contact_id = (string) ($_POST['id'] ?? '');

        $_POST['email_address'] = pg_contact_form_email($_POST['email_address'] ?? '');

        $columns = pg_contact_form_columns($_POST, $user['id']);
        $columns['parasut_contact_id'] = trim(pg_contact_form_posted($_POST, 'parasut_contact_id'));

        if (AFFILIATE_PROGRAM == true) {
            // determine if affiliate was approved already (we will use this later)
            $result = db_q("SELECT affiliate_approved FROM contacts WHERE id = ?", array($contact_id)) or output_error('Query failed');
            $row = mysqli_fetch_assoc($result);
            $original_affiliate_approved = $row['affiliate_approved'];

            $affiliate_code = pg_contact_form_affiliate_code($_POST, $contact_id);

            $columns += pg_contact_form_affiliate_columns($_POST, $affiliate_code);
        }

        $audit_before = pg_audit_fetch('contacts', (int) $contact_id);

        $assignments = array();

        foreach (array_keys($columns) as $column) {
            $assignments[] = $column . ' = ?';
        }

        db_q(
            "UPDATE contacts
            SET " . implode(', ', $assignments) . ", timestamp = UNIX_TIMESTAMP()
            WHERE id = ?",
            array_merge(array_values($columns), array($contact_id))) or output_error('Query failed');

        // The other contacts with this address take its opt-in status; this
        // contact's own line is written below.
        pg_contact_form_sync_opt_in((int) $contact_id, $_POST['email_address'], $columns['opt_in']);

        pg_audit_record('update', 'contacts', (int) $contact_id, $audit_before);

        pg_contact_form_save_groups($user, $contact_id, pg_contact_form_all_groups(), $_POST);

        // An affiliate approved just now gets a key code for the affiliate group offer.
        if ((AFFILIATE_PROGRAM == TRUE) && (($_POST['affiliate_approved'] ?? '') == 1) && ($original_affiliate_approved == 0)) {
            pg_contact_form_affiliate_key_code($affiliate_code, $user['id']);
        }

        log_activity( lang(array('string'=>'contact ({var:1} {var:2}) was modified','vars'=>array($_POST['first_name'],$_POST['last_name']) )), $_SESSION['sessionusername']);
          
        // if there is not a send to or the send to is the view contacts screen, then add notice
        if ((isset($_REQUEST['send_to']) == FALSE) || (mb_strpos($_REQUEST['send_to'], 'view_contacts.php') !== FALSE)) {
            $liveform_view_contacts = new liveform('view_contacts');
            $liveform_view_contacts->add_notice(lang('The contact has been saved.'));
        }
    }

    pg_contact_form_redirect();
}
?>