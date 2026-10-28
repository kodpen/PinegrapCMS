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
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM contact_groups WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// if user does not have access to edit contact group, output error
if (validate_contact_group_access($user, $_REQUEST['id']) == false) {
    log_activity(lang('access denied to edit contact group'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_group_form.php');
$liveform = new liveform('edit_contact_group', $_REQUEST['id']);

// get number of contacts in this contact group
$query = "SELECT COUNT(contact_id) FROM contacts_contact_groups_xref WHERE contact_group_id = ?";
$result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');
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
            WHERE id = ?";
        $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        $liveform->assign_field_value('name', $row['name']);
        $liveform->assign_field_value('email_subscription', $row['email_subscription']);
        $liveform->assign_field_value('email_subscription_type', $row['email_subscription_type']);
        $liveform->assign_field_value('description', $row['description']);
    }
    
    // if there are no contacts in this contact group, allow delete
    if ($number_of_contacts == 0) {
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('contact group')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>';
        
    // else there is at least one contact in this contact group, so disable delete button
    } else {
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " onclick="alert(\'' . lang('Please delete or remove all contacts from this contact group before deleting this contact group.') . '\')" ><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>';
    }

    print
    pg_page_shell(
        array(
            'title'=> lang('Edit Contact Group'),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang('Edit Contact Group'),
            'heading_description' => lang('Update this contact group and its subscription features.'),
            'cancel'=>array('enable'=>'true','url'=>'view_contact_groups.php'),
        
            'breadcrumb' => array(array('label' => lang('All Contact Groups'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contact_groups.php'), array('label' => lang('Edit Contact Group'))),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                
                <form name="form" action="edit_contact_group.php" method="post" >
                    ' . get_token_field() . '
                    ' . pg_contact_group_form_card($liveform) . '

                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
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
            $query = "SELECT name FROM contact_groups WHERE id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $contact_group_name = $row['name'];
            
            // delete contact group
            $query = "DELETE FROM contact_groups WHERE id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete contact group references in contacts_contact_groups_xref
            $query = "DELETE FROM contacts_contact_groups_xref WHERE contact_group_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete contact group references in users_contact_groups_xref
            $query = "DELETE FROM users_contact_groups_xref WHERE contact_group_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete contact group references in opt_in
            $query = "DELETE FROM opt_in WHERE contact_group_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete contact group references in contact_groups_email_campaigns_xref
            $query = "DELETE FROM contact_groups_email_campaigns_xref WHERE contact_group_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

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
        pg_contact_group_form_validate_name($liveform, (string) ($_POST['id'] ?? ''));
        
        // if there is an error, forward user back to edit contact group screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact_group.php?id=' . $_POST['id']);
            exit();
        }
        
        // update contact group
        $query =
            "UPDATE contact_groups
            SET
                name = ?,
                email_subscription = ?,
                email_subscription_type = ?,
                description = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        $result = db_q($query, array_merge(pg_contact_group_form_values($liveform), array((string) $user['id'], (string) ($_POST['id'] ?? '')))) or output_error('Query failed.');
        log_activity(lang(array('string'=>'contact group (name: {var:1}) was modified','vars'=>$liveform->get_field_value('name') )), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_contact_groups = new liveform('view_contact_groups');
        $liveform_view_contact_groups->add_notice(lang('The contact group has been saved.'));
        
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_contact_groups.php');
        
        
    }
}
?>