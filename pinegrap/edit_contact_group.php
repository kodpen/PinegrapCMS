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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_contacts_access($user);

// if user does not have access to edit contact group, output error
if (validate_contact_group_access($user, $_REQUEST['id']) == false) {
    log_activity(lang('access denied to edit contact group'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
$liveform = new liveform('edit_contact_group', $_REQUEST['id']);

// get number of contacts in this contact group
$query = "SELECT COUNT(contact_id) FROM contacts_contact_groups_xref WHERE contact_group_id = '" . escape($_REQUEST['id']) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$number_of_contacts = $row[0];

if (!$_POST) {
    // if edit contact group screen has not been submitted already, pre-populate fields with data
    if (isset($_SESSION['software']['liveforms']['edit_contact_group'][$_GET['id']]) == false) {
        // get contact group data
        $query =
            "SELECT
                name,
                email_subscription,
                email_subscription_type,
                description
            FROM contact_groups
            WHERE id = '" . escape($_GET['id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        $liveform->assign_field_value('name', $row['name']);
        $liveform->assign_field_value('email_subscription', $row['email_subscription']);
        $liveform->assign_field_value('email_subscription_type', $row['email_subscription_type']);
        $liveform->assign_field_value('description', $row['description']);
    }
    
    // if there are no contacts in this contact group, allow delete
    if ($number_of_contacts == 0) {
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('contact group')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';
        
    // else there is at least one contact in this contact group, so disable delete button
    } else {
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " onclick="alert(\'' . lang('Please delete or remove all contacts from this contact group before deleting this contact group.') . '\')" ><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';
    }

    print
    pg_page_shell(
        array(
            'title'=> lang('Edit Contact Group'),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang('Edit Contact Group'),
            'cancel'=>array('enable'=>'true','url'=>'view_contact_groups.php'),
        
            'breadcrumb' => array(array('label' => lang('All Contact Groups'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contact_groups.php'), array('label' => lang('Edit Contact Group'))),
        )
    ) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Update this contact group and its subscription features.') . '" title="' . lang('Edit Contact Group') . '">[' . h($row['name']) . ']</h2>
                    </div>
                </div>
                <form name="form" action="edit_contact_group.php" method="post" >
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="name" class="form-label">' . lang('Contact Group Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'placeholder'=>lang('Please enter a name'), 'maxlength'=>'255', 'class'=>'form-control add-header-content-updater')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>

                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'email_subscription', 'id'=>'email_subscription', 'value'=>'1', 'class'=>'form-check-input collapse-switcher', 'data-bs-target'=>'#email_subscription_type_row')) . '
                                                <label class="form-check-label" for="email_subscription">' . lang('Enable E-mail Subscription') . '</label>
                                                <div class="form-text">' . lang('Allow Campaigns to send to this Contact Group') . '</div>
                                            </div>
                                        </div>
                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="email_subscription_type_row">
                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                            <div class="popover-body">
                                                <div class="row">
                                                 
                                                    <div class="col-12 my-3">
                                                        <label class="form-label">' . lang('E-mail Subscription Type') . '</label>
                                                        <div class="form-check">
                                                        ' . $liveform->output_field(array('type'=>'radio', 'name'=>'email_subscription_type', 'id'=>'open', 'value'=>'open', 'checked'=>'checked', 'class'=>'form-check-input')) . '
                                                            <label class="form-check-label" for="open">' . lang('Open') . '</label>
                                                        </div>
                                                        <div class="form-check">
                                                            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'email_subscription_type', 'id'=>'closed', 'value'=>'closed', 'class'=>'form-check-input')) . '
                                                            <label class="form-check-label" for="closed">' . lang('Closed') . '</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 my-1">
                                                        <label for="description" class="form-label">' . lang('Description') . '</label>
                                                        ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'description', 'id'=>'description', 'class'=>'form-control', 'rows'=>'5', 'cols'=>'50')) . '
                                                        <div class="form-text text-end">' . lang('Description/Subscription Message on My Account Pages') . '</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                                ' . $output_delete_button . '
                            </div>
                        </div>
                    </nav>
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                </form>
            </div>
        </div>
    </main>' .
    output_footer();
    
    $liveform->remove_form();

} else {
    validate_token_field();
    
    // if contact group was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // if there are no contacts in this contact group, proceed with deleting contact group
        if ($number_of_contacts == 0) {
            // get contact group name for log
            $query = "SELECT name FROM contact_groups WHERE id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $contact_group_name = $row['name'];
            
            // delete contact group
            $query = "DELETE FROM contact_groups WHERE id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete contact group references in contacts_contact_groups_xref
            $query = "DELETE FROM contacts_contact_groups_xref WHERE contact_group_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete contact group references in users_contact_groups_xref
            $query = "DELETE FROM users_contact_groups_xref WHERE contact_group_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete contact group references in opt_in
            $query = "DELETE FROM opt_in WHERE contact_group_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // delete contact group references in contact_groups_email_campaigns_xref
            $query = "DELETE FROM contact_groups_email_campaigns_xref WHERE contact_group_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            log_activity(lang(array('string'=>'contact group ({var:1}) was deleted','vars'=>$contact_group_name)), $_SESSION['sessionusername']);
            $liveform->remove_form();
            $liveform_view_contact_groups = new liveform('view_contact_groups');
            $liveform_view_contact_groups->add_notice(lang('The contact group has been deleted.'));
            
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_contact_groups.php');
            
            
        
        // else there is at least one contact in this contact group, so prepare error
        } else {
            $liveform->add_fields_to_session();
            
            $liveform->mark_error('', lang('Please delete or remove all contacts from this contact group before deleting this contact group.'));
            
            // forward user to edit contact group screen
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact_group.php?id=' . $_POST['id']);
        }
        
    // else contact group was not selected for delete
    } else {
        $liveform->add_fields_to_session();
        $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required','vars'=>lang('Name'))));
        
        // if there is an error, forward user back to edit contact group screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact_group.php?id=' . $_POST['id']);
            exit();
        }
        
        // check to see if name is already in use by a different contact group
        $query =
            "SELECT id
            FROM contact_groups
            WHERE
                (name = '" . escape($liveform->get_field_value('name')) . "')
                AND (id != '" . escape($_POST['id'] ?? '') . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if name is already in use by a different contact group, prepare error and forward user back to screen
        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
            // forward user to edit contact group screen
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact_group.php?id=' . $_POST['id']);
            exit();
        }
        
        // update contact group
        $query =
            "UPDATE contact_groups
            SET
                name = '" . escape($liveform->get_field_value('name')) . "',
                email_subscription = '" . escape($liveform->get_field_value('email_subscription')) . "',
                email_subscription_type = '" . escape($liveform->get_field_value('email_subscription_type')) . "',
                description = '" . escape($liveform->get_field_value('description')) . "',
                last_modified_user_id = '" . $user['id'] . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        log_activity(lang(array('string'=>'contact group (name: {var:1}) was modified','vars'=>$liveform->get_field_value('name') )), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_contact_groups = new liveform('view_contact_groups');
        $liveform_view_contact_groups->add_notice(lang('The contact group has been saved.'));
        
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_contact_groups.php');
        
        
    }
}
?>