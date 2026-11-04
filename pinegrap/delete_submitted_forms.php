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
validate_forms_access($user);

validate_token_field();

include_once('liveform.class.php');
$liveform = new liveform('view_submitted_forms');

// The submitted forms list sends its selection as the list table driver
// keeps it: the forms picked, or the whole list by its filters less the
// exceptions. It is read back with the list's own query and the user's
// rights (pg_submitted_form_list_selected_ids()).
require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_writes.php');
$form_ids = pg_submitted_form_list_selected_ids($user, $_POST['selection'] ?? '');

if ($form_ids === false) {
    output_error(lang('Query failed.'));
}

if ($form_ids === null) {
    output_error(lang('Too many forms are selected. Select fewer forms and try again.') . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
}

// if at least one form was selected
if ($form_ids) {
    $number_of_forms = 0;

    // loop through all forms that were selected for deletion
    foreach ($form_ids as $form_id) {
        // get folder id so we can validate access to this form
        $query = "SELECT page.page_folder as folder_id
                 FROM forms
                 LEFT JOIN page ON forms.page_id = page.page_id
                 WHERE forms.id = ?";
        $result = db_q($query, array((string) $form_id)) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        // A form deleted meanwhile (another tab, another user) is passed over
        // and not counted: check_edit_access() of no folder is true for a
        // manager.
        if (!$row) {
            continue;
        }

        // if user has access to edit this form's custom form then user has access to delete form
        if (check_edit_access($row['folder_id']) == true) {
            // The record with its files, signatures, views and comment thread.
            if (pg_submitted_form_delete($form_id) === false) {
                output_error(lang('Query failed.'));
            }

            $number_of_forms++;
        }
    }
    
    // if more than 0 forms were deleted, then log activity
    if ($number_of_forms > 0) {
        log_activity(lang(array('string'=>'{var:1} submitted form(s) were deleted','vars'=>$number_of_forms)), $_SESSION['sessionusername']);
        $liveform->add_notice(lang(array('string'=>'{var:1} form(s) have been deleted.','vars'=>$number_of_forms)) );
    } else {
        $liveform->mark_error('no_forms_selected_error', lang('No forms were deleted'));
    }
    
// else no forms were selected
} else {
    $liveform->mark_error('no_forms_selected_error', lang('No forms were deleted because none were selected.'));
}

// forward user to view forms screen
header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_submitted_forms.php');
?>