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

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM forms WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// Get info about submitted form.
$query =
    "SELECT 
        page.page_folder as folder_id,
        forms.user_id,
        forms.page_id,
        forms.form_editor_user_id,
        custom_form_pages.form_name,
        forms.reference_code,
        forms.complete,
        forms.address_name,
        forms.contact_id
    FROM forms
    LEFT JOIN page on forms.page_id = page.page_id
    LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
    WHERE forms.id = ?";
$result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

// The id comes straight from the query string, so it can point at a form that
// was deleted (or never existed). Without this the 17 reads below all run
// against null.
if (!$row) {
    output_error(lang('Sorry, the item could not be found.'), 404);
}

$folder_id = $row['folder_id'];
$custom_form_page_id = $row['page_id'];
$submitter_id = $row['user_id'];
$form_editor_user_id = $row['form_editor_user_id'];
$form_name = $row['form_name'];
$reference_code = $row['reference_code'];
$complete = $row['complete'];
$old_address_name = $row['address_name'];
$contact_id = $row['contact_id'];

// If there is a form_item_view_page_id submitted in the post,
// then get properties for that page.  We will use these properties
// in several places further below.
if (!empty($_POST['form_item_view_page_id'])) {
    $query =
        "SELECT 
            custom_form_page_id,
            submitted_form_editable_by_registered_user,
            submitted_form_editable_by_submitter,
            hook_code
        FROM form_item_view_pages
        WHERE
            (page_id = ?)
            AND (collection = 'a')";
    $result = db_q($query, array((string) ($_POST['form_item_view_page_id'] ?? ''))) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $form_item_view_custom_form_page_id = $row['custom_form_page_id'];
    $submitted_form_editable_by_registered_user = $row['submitted_form_editable_by_registered_user'];
    $submitted_form_editable_by_submitter = $row['submitted_form_editable_by_submitter'];
    $hook_code = $row['hook_code'];
}

// if user is a user level and does not have access to manage forms or the user does not have access to edit the folder the form is in
if (
    ($user['role'] > 2)
    && (($user['manage_forms'] == false) || (check_edit_access($folder_id) == false))
) {
    // remember that the user is not a submitted forms manager for this form (we will use this later)
    $submitted_forms_manager = FALSE;
    
    // If there is a form_item_view_page_id submitted in the post
    if (!empty($_POST['form_item_view_page_id'])) {
        // If the form_item_view page does not belong to the custom form being submitted, output error
        if ($form_item_view_custom_form_page_id != $custom_form_page_id) {
            log_activity(lang('access denied to edit form submission'), $_SESSION['sessionusername']);
            output_error(lang('Access denied. The submitted form item view page does not match the custom form id.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        
        // If the form is not editable by just any registered user
        if ($submitted_form_editable_by_registered_user == '0') {
            $edit_access = false;

            // If the user is the submitter and the form is incomplete,
            // or the user is the submitter and the page allows submitter to edit,
            // or if this user is the form editor, then the user has access.
            if (
                (($user['id'] == $submitter_id) and !$complete)
                or (($user['id'] == $submitter_id) and $submitted_form_editable_by_submitter)
                or ($user['id'] == $form_editor_user_id)
            ) {
                $edit_access = true;
            }

            if (!$edit_access) {
                log_activity(lang('access denied to forms'), $_SESSION['sessionusername']);
                output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }
        }
    // Else, there was not a form_item_view_page_id submitted in the post, so output error
    } else {
        log_activity(lang('access denied to forms'), $_SESSION['sessionusername']);
        output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }
    
// else remember that the user is a submitted forms manager (we will use this later)
} else {
    $submitted_forms_manager = TRUE;
}

$pretty_urls = check_if_pretty_urls_are_enabled($custom_form_page_id);

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/submitted_form_form.php');
$liveform = new liveform('edit_submitted_form', $_REQUEST['id']);

// An attachment over post_max_size makes PHP throw the whole request away.
// The id and the screen to return to ride in the form's address, so the
// record comes back with what happened instead of "Record not found".
if (pg_post_body_discarded()) {
    $liveform->mark_error('', h(pg_submitted_form_discarded_message()));
    $discarded_send_to = pg_safe_redirect_path(pg_send_to_url(''), '/__none__');
    go(PATH . SOFTWARE_DIRECTORY . '/edit_submitted_form.php?id=' . $id . (($discarded_send_to !== '/__none__') ? '&send_to=' . urlencode($discarded_send_to) : ''));
}

// if form has not been submitted yet
if (!$_POST) {
    // get form information
    $query =
        "SELECT
            custom_form_pages.form_name,
            custom_form_pages.quiz,
            forms.page_id,
            forms.quiz_score,
            forms.reference_code,
            forms.complete,
            forms.tracking_code,
            forms.affiliate_code,
            forms.http_referer,
            INET_NTOA(forms.ip_address) as ip_address,
            form_editor_user.user_username as form_editor_username,
            contacts.member_id,
            submitted_user.user_username as submitted_username,
            forms.submitted_timestamp,
            last_modified_user.user_username as last_modified_username,
            forms.last_modified_timestamp,
            custom_form_pages.label_column_width
        FROM forms
        LEFT JOIN custom_form_pages on forms.page_id = custom_form_pages.page_id
        LEFT JOIN contacts ON forms.contact_id = contacts.id
        LEFT JOIN user as form_editor_user ON forms.form_editor_user_id = form_editor_user.user_id
        LEFT JOIN user as submitted_user ON forms.user_id = submitted_user.user_id
        LEFT JOIN user as last_modified_user ON forms.last_modified_user_id = last_modified_user.user_id
        WHERE forms.id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    // Same as above: a stale id gives null here, and the 17 reads that follow
    // would each warn and then render blank.
    if (!$row) {
        output_error(lang('Sorry, the item could not be found.'), 404);
    }

    $form_name = $row['form_name'];
    $quiz = $row['quiz'];
    $page_id = $row['page_id'];
    $quiz_score = $row['quiz_score'];
    $reference_code = $row['reference_code'];
    $complete = $row['complete'];
    $tracking_code = $row['tracking_code'];
    $affiliate_code = $row['affiliate_code'];
    $http_referer = $row['http_referer'];
    $ip_address = $row['ip_address'];
    $form_editor_username = $row['form_editor_username'];
    $member_id = $row['member_id'];
    $submitted_username = $row['submitted_username'];
    $submitted_timestamp = $row['submitted_timestamp'];
    $last_modified_username = $row['last_modified_username'];
    $last_modified_timestamp = $row['last_modified_timestamp'];
    $label_column_width = $row['label_column_width'];
    
    $output_quiz_score_row = '';

    // if this is for a quiz custom form, prepare to output quiz score row
    if ($quiz == 1) {
        $output_quiz_score_row =
            '<div class="col-12 md:col-6 lg:col-4 my-3">
                <div class="form-text">' . lang('Quiz Score') . '</div>
                <div class="h5">' . $quiz_score . '</div>
            </div>';
    }

    if ($complete) {
        $status = '<span class="badge theme-success fw-light">' . lang('Complete') . '</span>';
    } else {
        $status = '<span class="badge theme-secondary fw-light">' . lang('Incomplete') . '</span>';
    }

    $output_address_name = '';
    
    // If pretty URLs are enabled, then output address name.
    if ($pretty_urls == true) {
        $output_address_name = h($old_address_name);
    }
    
    $output_affiliate_code = '';

    if (AFFILIATE_PROGRAM == true) {
        $output_affiliate_code =
            '<div class="col-12 md:col-6 lg:col-4 my-3">
                <div class="form-text">' . lang('Affiliate Code') . '</div>
                <div class="h5">' . h($affiliate_code ? $affiliate_code : '-') . '</div>
            </div>';
    }
    
    // if http referer is greater than 25 characters, then shorten text version
    if (mb_strlen($http_referer) > 25) {
        $http_referer_text = mb_substr($http_referer, 0, 25) . '...';
    } else {
        $http_referer_text = $http_referer;
    }

    // If we don't know the IP address for the submitted form, then set it to empty string.
    if ($ip_address == '0.0.0.0') {
        $output_ip_address = '-';

    // Otherwise, we do know the IP address, so output it.
    } else {
        $output_ip_address = $ip_address;
    }
    

    if ($submitted_username) {
        $submitted_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($submitted_username) ) ) );
    }else{
        $submitted_username = ' ' . lang(array('string'=>'by [{var:1}]','vars'=>lang('Unknown') ) );
    }

    if ($last_modified_username) {
        $last_modified_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($last_modified_username) ) ) );
    }else{
        $last_modified_username = ' ' . lang(array('string'=>'by [{var:1}]','vars'=>lang('Unknown') ) );
    }

    
    // Whether the form is filled from the stored item (no posted values
    // waiting from a save that came back with errors).
    $prefill = (isset($_SESSION['software']['liveforms']['edit_submitted_form'][$_GET['id']]) == false);

    // if edit submitted form screen has not been submitted already, pre-populate fields with form data
    if ($prefill) {
        $query = "SELECT
                    form_data.form_field_id,
                    form_data.data,
                    count(*) as number_of_values,
                    form_fields.type,
                    form_fields.wysiwyg
                 FROM form_data
                 LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
                 WHERE
                    (form_data.form_id = ?)
                    AND (form_fields.type != 'file upload')
                    AND (form_fields.type != 'signature')
                 GROUP BY form_data.form_field_id";
        $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');

        $fields = array();

        while ($row = mysqli_fetch_assoc($result)) {
            $fields[] = $row;
        }
        
        // loop through all field data in order to add it to liveform session
        foreach ($fields as $field) {
            // if there is more than one value, get all values
            if ($field['number_of_values'] > 1) {
                $query = "SELECT data
                         FROM form_data
                         WHERE (form_id = ?) AND (form_field_id = ?)
                         ORDER BY id";
                $result = db_q($query, array((string) $_GET['id'], (string) $field['form_field_id'])) or output_error('Query failed.');
                
                $field['data'] = array();
                
                while ($row = mysqli_fetch_assoc($result)) {
                    $field['data'][] = $row['data'];
                }
            }
            
            // A rich-text field loads into the editor, which parses the value
            // as HTML. Rows stored as 'standard' (answers saved before the
            // field became rich text, API writes) were never filtered, so the
            // value goes through the staff profile first.
            if (
                ($field['type'] == 'text area')
                && ($field['wysiwyg'] == 1)
                && is_string($field['data'])
            ) {
                $field['data'] = prepare_rich_text_editor_content_for_output(pg_sanitize_rich_text($field['data'], 'staff'));
            }
            
            $liveform->assign_field_value($field['form_field_id'], prepare_form_data_for_output($field['data'], $field['type'], $prepare_for_html = false));
        }
    }
    
    // The History section of a collection item: Compare shows one revision
    // beside the current values, Load fills the form with one; saving a
    // loaded revision records it with source 'restore'.
    $history_enabled = pg_record_revision_history_enabled($page_id);
    $history_revision = null;
    $history_notes = array();

    if ($history_enabled && !empty($_GET['revision']) && is_scalar($_GET['revision'])) {
        $history_revision = pg_record_revision_get($_GET['id'], (int) $_GET['revision']);

        if ($history_revision) {
            $history_notes = pg_record_revision_load($liveform, $_GET['id'], $page_id, $history_revision, $prefill);
        }
    }

    // Office use only fields are shown to a manager and above, and to a user who
    // manages forms and may edit the custom form's folder.
    $office_use_only_access = (
        ($user['role'] < 3)
        || ((check_edit_access($folder_id) == true) && ($user['manage_forms'] == TRUE))
    );

    $form_markup = pg_submitted_form_fields_markup($liveform, $user, $custom_form_page_id, $office_use_only_access, (string) $_GET['id']);

    // If the form is incomplete then show certain buttons.
    if (!$complete) {
        $output_buttons =
            '<button type="submit" name="save_for_later_button" value="Save for Later" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save_as</span><span class="pg-btn-label" >' . lang(array('string'=>'Save for Later') ) . '</span></button>
            <button type="submit" name="complete_button" value="Complete" class="btn my-1  btn-solid theme-secondary"><span class="material-icons me-3">radio_button_unchecked</span><span class="pg-btn-label">' . lang(array('string'=>'Convert Complete') ) . '</span></button>';

    // Otherwise the form is complete, so show different buttons.
    } else {
        $output_buttons =
            '<button type="submit" name="save_button" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
            <button type="submit" name="incomplete_button" value="Incomplete" class="btn my-1  btn-solid theme-warning "><span class="material-icons me-3">radio_button_checked</span><span class="pg-btn-label">' . lang(array('string'=>'Convert Incomplete') ) . '</span></button>';
    }
    
    // Where it was talked about in the workspace, and the tasks about it.
    $output_workspace_button = '';

    if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
        $output_workspace_button = ws_record_button($user, 'form', (int) $_GET['id'], trim($form_name . ' ' . $reference_code));
    }

    // The page a site's Edit button opened this screen from
    // (pg_sw_backend_edit_url()): saving or cancelling goes back there.
    // Only a path on this site.
    $send_to = pg_safe_redirect_path(pg_send_to_url(''), '/__none__');
    if ($send_to === '/__none__') $send_to = '';

    echo
    pg_page_shell(
        array(
            'title'=> lang('Edit Submitted Form'),
            'extra classes'=>'form',
            'icon'=>'form',
            'heading'=>lang('Edit Submitted Form'),
            'heading_description' => lang('View or update this submitted form. Office use only fields are also visible.'),
            'cancel'=>array('enable'=>'true','url'=>($send_to !== '') ? $send_to : 'view_submitted_forms.php'),
            'breadcrumb' => array(
                array('label' => lang('Forms'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_submitted_forms.php'),
                array('label' => lang('Edit Submitted Form')),
            ),
        )
    ) . '
<main id="content" class="container-fluid">
            ' . $form_markup['wysiwyg_javascript'] . '
        <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">
                        <div class="row mb-3">
                            <div class="col-12 md:col">
                                
                                <p class="p-0 m-0">' . lang('Reference Code') . ': ' . $reference_code . '</p>
                                <p class="p-0 m-0">' . lang('Submitted') . ': ' . get_relative_time(array('timestamp' => $submitted_timestamp)) . ' ' . $submitted_username . '</p>
                            </div>
                            <div class="col-12 md:col-auto">' . $output_workspace_button . '</div>
                        </div>
                    </div>
                    <form' . $form_markup['enctype'] . ' name="form" action="edit_submitted_form.php?id=' . $id . (($send_to !== '') ? '&amp;send_to=' . h(urlencode($send_to)) : '') . '" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                        ' . (($send_to !== '') ? '<input type="hidden" name="send_to" value="' . h($send_to) . '" />' : '') . '
                        ' . ($history_revision ? '<input type="hidden" name="restore_revision" value="' . (int) $history_revision['revision_no'] . '" />' : '') . '
                        <div class="row">
                            <div class="col-12">
                                <div class="card my-7 ">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Form Properties') . '
                                    </div>
                                    <div class="card-body text-center md:text-start">
                                        <div class="row">
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <div class="form-text">' . lang('Custom Form') . '</div>
                                                <div class="h5">' . h($form_name) . '</div>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <div class="form-text">' . lang('Reference Code') . '</div>
                                                <div class="h5">' . $reference_code . '</div>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <div class="form-text">' . lang('Status') . '</div>
                                                ' . $status . '
                                            </div>
                                            ' . $output_quiz_score_row . '
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <div class="form-text">' . lang('Address Name') . '</div>
                                                <div class="h6">' . ($output_address_name ? $output_address_name : '-') . '</div>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <div class="form-text">' . lang('Tracking Code') . '</div>
                                                <div class="h5">' . h($tracking_code ? $tracking_code : '-') . '</div>
                                            </div>
                                            ' . $output_affiliate_code . '
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <div class="form-text">' . lang('Referring URL') . '</div>
                                                ' . ($http_referer_text ? '<a class="pg-link-secondary" href="' . h(escape_url($http_referer)) . '" target="_blank">' . h($http_referer_text) . '</a>' : '-') . '
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <div class="form-text">' . h(MEMBER_ID_LABEL) . '</div>
                                                <div class="h5">' . h($member_id ? $member_id : '-') . '</div>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <div class="form-text">' . lang('IP Address') . '</div>
                                                <div class="h5">' . $output_ip_address . '</div>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <div class="form-text">' . lang('Submitted Date') . '</div>
                                                <div class="h6">' . get_relative_time(array('timestamp' => $submitted_timestamp)) . ' ' . $submitted_username . '</div>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <div class="form-text">' . lang('Last Modified Date') . '</div>
                                                <div class="h6">' . get_relative_time(array('timestamp' => $last_modified_timestamp)) . ' ' . $last_modified_username . '</div>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="form_editor_username" class="form-text">' . lang('Form Editor') . '</label>
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'form_editor_username', 'id'=>'form_editor_username', 'value'=>$form_editor_username, 'class'=>'form-control', 'maxlength'=>'100')) . '
                                                <div class="form-text text-end">' . lang('enter username') . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card my-7">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Form Data') . '
                                    </div>
                                    <div class="card-body">
                                        ' . ($history_revision ? pg_record_revision_loaded_notice($history_revision, $history_notes) : '') . '
                                        <div class="row">
                                            ' . $form_markup['fields'] . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class=" btn-group flex-wrap justify-content-center">
                                    ' . $output_buttons . '
                                    <button type="submit" name="delete_button" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('submitted form')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
                    ' . ($history_enabled ? '<div class="col-12">' . pg_record_revision_history_markup($_GET['id'], $page_id, (is_scalar($_GET['compare'] ?? null) ? (int) $_GET['compare'] : 0), $send_to) . '</div>' : '') . '
                </div>
            </div>
        </div>
    
    ' . pg_ai_write_assets() . '
</main>' . output_footer();
    
    $liveform->remove_form();
    
// else form has been submitted
} else {
    validate_token_field();
    
    // if form was selected for deletion
    if (isset($_POST['delete_button'])) {
        // assume that the user does not have delete access until we find out otherwise
        $delete_access = FALSE;
        
        // if the user is greater than a user role,
        // or if user has access to manage forms and can edit the folder the form is in,
        // or if the user is deleting this from a form item view page and submitted forms are editable by a registered user from the form item view page,
        // or if the user is deleting this from a form item view page and the user is the submitter and the form item view page allows users to edit their submissions,
        // then the user has access to edit and delete submitted form
        // the only type of user that can edit but cannot delete are form editors
        if (
            ($user['role'] < 3)
            || ((check_edit_access($folder_id) == true) && ($user['manage_forms'] == true))
            || ((!empty($_POST['form_item_view_page_id'])) && ($submitted_form_editable_by_registered_user == '1'))
            || ((!empty($_POST['form_item_view_page_id'])) && ($user['id'] == $submitter_id) && ($submitted_form_editable_by_submitter == '1'))
        ) {
            $delete_access = TRUE;
        }
        
        // if the user does not have delete access (e.g. form editor), then log activity and output error
        if ($delete_access == FALSE) {
            log_activity(lang('access denied to delete submitted form'), $_SESSION['sessionusername']);
            output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        
        // get uploaded files for this form, so they can be deleted
        $query = "SELECT
                    files.id,
                    files.name
                 FROM form_data
                 LEFT JOIN files ON form_data.file_id = files.id
                 WHERE (form_data.form_id = ?) AND (form_data.file_id > 0)";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        
        $files = array();
        
        while($row = mysqli_fetch_assoc($result)) {
            $files[] = $row;
        }
        
        // loop through all files so they can be deleted
        foreach ($files as $file) {
            // if file still exists, delete file
            if ($file['id']) {
                // delete file record
                $query = "DELETE FROM files WHERE id = ?";
                $result = db_q($query, array((string) $file['id'])) or output_error('Query failed.');
                
                // delete file
                @unlink(FILE_DIRECTORY_PATH . '/' . $file['name']);
                log_activity(lang(array('string'=>'file ({var:1}) was deleted because the submitted form (form name: {var:2}, reference code: {var:3}) for the file was deleted','vars'=>array($file['name'],$form_name,$reference_code) )), $_SESSION['sessionusername']);
            }
        }
        
        // delete form
        $query = "DELETE FROM forms WHERE id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        
        // delete form data
        $query = "DELETE FROM form_data WHERE (form_id = ?) AND (form_id != '0')";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        // delete signature records for this submitted form
        pg_signature_delete($_POST['id'] ?? 0);
        
        // delete views for this submitted form that the form view directory feature uses
        pg_sfv_delete_views('submitted_form_id', $_POST['id']);

        log_activity(lang(array('string'=>'submitted form (form name: {var:1}, reference code: {var:2}) was deleted','vars'=>array($form_name,$reference_code) )), $_SESSION['sessionusername']);
        
        // if the there is a form list view send to, then forward user there
        if ((isset($_POST['form_list_view_send_to']) == TRUE) && ($_POST['form_list_view_send_to'] != '')) {
            // Get the form list view page id for this form item view in order to setup liveform correctly.
            $query = "SELECT page_id FROM form_list_view_pages WHERE form_item_view_page_id = ?";
            $result = db_q($query, array((string) ($_POST['form_item_view_page_id'] ?? ''))) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $form_list_view_page_id = $row['page_id'];

            // If a form list view page was found, then add notice.
            if ($form_list_view_page_id != '') {
                $liveform->remove_form();
                $liveform_form_list_view = new liveform('form_list_view', $form_list_view_page_id);
                
                // add notice that submitted form has been deleted
                $liveform_form_list_view->add_notice(lang('The submitted form has been deleted.'));
            }
            
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_POST['form_list_view_send_to'] ?? '')));
            
        // else forward user to view submitted forms in backend
        } else {
            $liveform->remove_form();
            $liveform_view_submitted_forms = new liveform('view_submitted_forms');
            
            // add notice that submitted form has been deleted
            $liveform_view_submitted_forms->add_notice(lang('The submitted form has been deleted.'));
            
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_submitted_forms.php');
        }
        
        
        
    // else form was not selected for deletion, so form is being edited
    } else {
        $liveform->add_fields_to_session();
        
        $sql_form_editor = "";
        
        // if the user is a submitted forms manager for this submitted form
        // (i.e. manager or above or has access to manage forms and has edit access to the folder for the custom form)
        // and the form editor field appeared on the form
        // then prepare SQL to update form editor
        if (
            ($submitted_forms_manager == TRUE)
            && ($liveform->field_in_session('form_editor_username') == TRUE)
        ) {
            // if a username was entered, then validate username
            if ($liveform->get_field_value('form_editor_username') != '') {
                // try to find a user with the username that was entered for the form editor
                $query = "SELECT user_id FROM user WHERE user_username = ?";
                $result = db_q($query, array((string) $liveform->get_field_value('form_editor_username'))) or output_error('Query failed.');
                
                // if a user was found, then prepare SQL for updating form editor
                if (mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                    $sql_form_editor = "form_editor_user_id = '" . $row['user_id'] . "',";
                    
                // else a user was not found, so prepare error
                } else {
                    $liveform->mark_error('form_editor_username', lang('Please enter a valid username for the form editor.'));
                }
                
            // else a username was not entered, so prepare to set an empty form editor
            } else {
                $sql_form_editor = "form_editor_user_id = '0',";
            }
        }
        
        // get page id
        $query = "SELECT page_id
                 FROM forms
                 WHERE forms.id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        $page_id = $row['page_id'];
        
        // get all fields for this form
        $fields = db_items(
            "SELECT
                id,
                name,
                rss_field,
                label,
                type,
                wysiwyg,
                contact_field,
                required,
                office_use_only,
                upload_folder_id
             FROM form_fields
             WHERE
                (page_id = '$page_id')
                AND (type != 'information')
             ORDER BY sort_order");

        // Loop through fields in order to validate them.
        foreach ($fields as $field) {
            // If field is required and the visitor clicked complete button
            // (for incomplete form) or save button (for complete form)
            // then determine if field appeared on form and should be required.
            if (
                $field['required']
                and
                (
                    $liveform->field_in_session('complete_button')
                    or $liveform->field_in_session('save_button')
                )
            ) {
                // If field is a file upload type then determine if field should be required, in a certain way.
                if ($field['type'] == 'file upload') {
                    // If field appeared on form and a file was not uploaded as a replacement, then check if there is an existing file.
                    if (
                        (isset($_FILES[$field['id']]) == true)
                        && ($_FILES[$field['id']]['name'] == '')
                    ) {
                        $file_id = db_value(
                            "SELECT files.id
                            FROM form_data
                            LEFT JOIN files on form_data.file_id = files.id
                            WHERE
                                (form_data.form_id = '" . escape($_POST['id'] ?? '') . "')
                                AND (form_data.form_field_id = '" . $field['id'] . "')");

                        // If there is not an existing file, then add error.
                        if ($file_id == '') {
                            $error_message = '';
                            
                            if ($field['label']) {
                                $error_message = lang(array('string'=>'{var:1} is required.','vars'=>h($field['label'])));
                            }
                            
                            $liveform->mark_error($field['id'], $error_message);
                        }
                    }
                    
                // Otherwise field is not a file upload type, so determine if field should be required, in a different way.
                } else {
                    // If field appeared on form, then require field.
                    if (isset($_POST[$field['id']]) == true) {
                        $error_message = '';
                        
                        if ($field['label']) {
                            $error_message = lang(array('string'=>'{var:1} is required.','vars'=>h($field['label'])));
                        }
                        
                        $liveform->validate_required_field($field['id'], $error_message);
                    }
                }
            }

            pg_submitted_form_check_field($liveform, $field, $page_id, $pretty_urls, (string) ($_POST['id'] ?? ''));
        }

        // If hooks are enabled and visitor is editing this form from a form item view page,
        // and there is hook code, then run it.
        if (
            (defined('PHP_REGIONS') and PHP_REGIONS === true)
            && (!empty($_POST['form_item_view_page_id']))
            && ($hook_code != '')
        ) {
            eval(prepare_for_eval($hook_code));
        }
        
        // if an error does not exist
        if ($liveform->check_form_errors() == false) {


            // If an incomplete button was clicked, then mark form as incomplete.
            if (
                $liveform->field_in_session('save_for_later_button')
                or $liveform->field_in_session('incomplete_button')
            ) {
                $new_complete = 0;

            // Otherwise a complete button was clicked, so remember that.
            } else {
                $new_complete = 1;
            }

            // The item as it was, for its revision (collection items only;
            // includes/fn/record_revisions.php).
            $revision_before = pg_record_revision_before($_POST['id'] ?? 0);

            // A revision loaded from the History section: only the panel's
            // own users load one, and only a revision of this item counts.
            $restore_revision = null;

            if ($submitted_forms_manager && $revision_before && !empty($_POST['restore_revision']) && is_scalar($_POST['restore_revision'])) {
                $restore_revision = pg_record_revision_get($_POST['id'] ?? 0, (int) $_POST['restore_revision']);
            }

            // File fields this save uploaded to or emptied itself: a restore
            // leaves them as this save made them.
            $files_changed = array();

            // update form
            $query = "UPDATE forms
                     SET
                        complete = '$new_complete',
                        $sql_form_editor
                        last_modified_user_id = '" . $user['id'] . "',
                        last_modified_timestamp = UNIX_TIMESTAMP()
                     WHERE id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            // Loop through all fields in order to save data for each one.
            foreach ($fields as $field) {
                // if this is not an office use only field or the user has access to edit office use only fields, then update field
                if (
                    ($field['office_use_only'] == 0)
                    || ($user['role'] < 3)
                    || ((check_edit_access($folder_id) == true) && ($user['manage_forms'] == TRUE))
                    || ($user['id'] == $form_editor_user_id)
                ) {
                    // If field is a file upload type then save data in a certain way.
                    if ($field['type'] == 'file upload') {
                        // If this field appeared on the form, then determine if we need to save data for it.
                        if (isset($_FILES[$field['id']]) == true) {
                            // If a new file was uploaded, then deal with that.
                            if ($_FILES[$field['id']]['name'] != '') {
                                $files_changed[] = $field['id'];

                                // Check if there is an existing file.
                                $file = db_item(
                                    "SELECT
                                        files.id,
                                        files.name
                                    FROM form_data
                                    LEFT JOIN files on form_data.file_id = files.id
                                    WHERE
                                        (form_data.form_id = '" . escape($_POST['id'] ?? '') . "')
                                        AND (form_data.form_field_id = '" . $field['id'] . "')");

                                // If there is an existing file, then delete it. A submission
                                // without a row for the field gives null here.
                                if ($file && $file['id'] != '') {
                                    // Delete file record in database.
                                    db("DELETE FROM files WHERE id = '" . $file['id'] . "'");
                                    
                                    // Delete file on file system.
                                    @unlink(FILE_DIRECTORY_PATH . '/' . $file['name']);
                                    log_activity(lang(array('string'=>'file ({var:1}) was deleted because a visitor uploaded a new file to replace it for the submitted form (form name: {var:2}, reference code: {var:3})','vars'=>array($file['name'],$form_name,$reference_code) )), $_SESSION['sessionusername']);
                                }

                                // Delete existing form_data record if one exists.
                                db(
                                    "DELETE FROM form_data
                                    WHERE
                                        (form_id = '" . escape($_POST['id'] ?? '') . "')
                                        AND (form_id != '0')
                                        AND (form_field_id = '" . $field['id'] . "')");

                                $user_id = '';

                                // If the user is logged in, then store user id.
                                if (USER_LOGGED_IN == true) {
                                    $user_id = USER_ID;
                                }

                                $file_id = pg_submitted_form_store_upload($field, $user_id);

                                db(
                                    "INSERT INTO form_data (
                                        form_id,
                                        form_field_id,
                                        file_id,
                                        name)
                                    VALUES (
                                        '" . escape($_POST['id'] ?? '') . "',
                                        '" . $field['id'] . "',
                                        '" . $file_id . "',
                                        '" . escape($field['name']) . "')");

                            // Otherwise if this field is optional and the existing file was set to be deleted, then delete it.
                            } else if (
                                ($field['required'] == 0)
                                && ($liveform->get_field_value($field['id'] . '_delete_file') == 1)
                            ) {
                                $files_changed[] = $field['id'];

                                // Check if there is an existing file.
                                $file = db_item(
                                    "SELECT
                                        files.id,
                                        files.name
                                    FROM form_data
                                    LEFT JOIN files on form_data.file_id = files.id
                                    WHERE
                                        (form_data.form_id = '" . escape($_POST['id'] ?? '') . "')
                                        AND (form_data.form_field_id = '" . $field['id'] . "')");

                                // If there is an existing file, then delete it. A submission
                                // without a row for the field gives null here.
                                if ($file && $file['id'] != '') {
                                    // Delete file record in database.
                                    db("DELETE FROM files WHERE id = '" . $file['id'] . "'");
                                    
                                    // Delete file on file system.
                                    @unlink(FILE_DIRECTORY_PATH . '/' . $file['name']);
                                    log_activity(lang(array('string'=>'file ({var:1}) was deleted for the submitted form (form name: {var:2}, reference code: {var:3})','vars'=>array($file['name'],$form_name,$reference_code) )), $_SESSION['sessionusername']);
                                }

                                // Delete existing form_data record if one exists.
                                db(
                                    "DELETE FROM form_data
                                    WHERE
                                        (form_id = '" . escape($_POST['id'] ?? '') . "')
                                        AND (form_id != '0')
                                        AND (form_field_id = '" . $field['id'] . "')");

                                // Insert blank form_data record.  We are not sure if this is necessary,
                                // however, we appear to do this when a submitted form is originally created,
                                // when a file is not uploaded, so we have decided to also do it here.
                                db(
                                    "INSERT INTO form_data (
                                        form_id,
                                        form_field_id,
                                        name)
                                    VALUES (
                                        '" . escape($_POST['id'] ?? '') . "',
                                        '" . $field['id'] . "',
                                        '" . escape($field['name']) . "')");
                            }
                        }

                    // Otherwise the field is not a file upload type so save data in a different way.
                    } else {
                        // if field appeared on form
                        if (isset($_POST[$field['id']]) == true) {
                            // delete existing values for this field
                            $query = "DELETE FROM form_data
                                     WHERE (form_id = ?) AND (form_id != '0') AND (form_field_id = ?)";
                            $result = db_q($query, array((string) ($_POST['id'] ?? ''), (string) $field['id'])) or output_error('Query failed.');
                            
                            pg_submitted_form_save_value($liveform, $_POST['id'] ?? '', $field);
                        }
                    }
                }
            }

            if ($restore_revision) {
                pg_record_revision_restore_files($_POST['id'] ?? 0, $page_id, $restore_revision, $files_changed);
            }

            // If pretty URLs are enabled, then update address name.
            if ($pretty_urls == true) {
                $new_address_name = update_submitted_form_address_name($_POST['id']);
            }

            pg_record_revision_after($_POST['id'] ?? 0, $revision_before, $restore_revision ? 'restore' : 'edit', $user['id']);

            // If the form was incomplete before and has now been completed,
            // then check if custom form actions should be completed (e.g. email alerts).
            if (!$complete and $new_complete) {
                // Send notification e-mails and create auto e-mail campaigns.
                // This logic lives in functions.php so that add_submitted_form.php
                // can reuse it.  The true argument preserves the original behavior
                // of only acting on custom forms that have save-for-later enabled.
                pg_send_custom_form_notifications($custom_form_page_id, $_POST['id'], $contact_id, true);
            }
            
            log_activity(lang(array('string'=>'submitted form (form name: {var:1}, reference code: {var:2}) was modified','vars'=>array($form_name,$reference_code))), $_SESSION['sessionusername']);

            // If the save-for-later button was clicked.
            if ($liveform->field_in_session('save_for_later_button')) {
                $message = lang('The form has been saved for later.');

            // Otherwise if the complete button was clicked.
            } else if ($liveform->field_in_session('complete_button')) {
                $message = lang('The form has been completed.');

            // Otherwise if the save button was clicked.
            } else if ($liveform->field_in_session('save_button')) {
                $message = lang('The form has been saved.');

            // Otherwise if the incomplete button was clicked.
            } else if ($liveform->field_in_session('incomplete_button')) {
                $message = lang('The form has been saved and marked as incomplete.');
            }
            
            // if there is a send to, then forward user to send to
            if ((isset($_POST['send_to']) == TRUE) && ($_POST['send_to'] != '')) {
                $liveform->remove_form();
                $liveform_form_item_view = new liveform('form_item_view');
                
                // add notice that submitted form has been saved
                $liveform_form_item_view->add_notice($message);

                $send_to = $_POST['send_to'];

                // If the visitor is not at an ugly URL and pretty URLs are enabled,
                // and the address name has changed, then replace old address name in send to,
                // with new address name, so that we don't forward the visitor to an invalid address.
                if (
                    (mb_strpos(mb_strtolower($send_to), 'r=') === false)
                    && ($pretty_urls == true)
                    && ($new_address_name != $old_address_name)
                ) {
                    $send_to_parts = parse_url($send_to);

                    $path_without_address_name = mb_substr($send_to_parts['path'], 0, mb_strrpos($send_to_parts['path'], '/') + 1);

                    $send_to = $path_without_address_name . $new_address_name;

                    if ($send_to_parts['query'] != '') {
                        $send_to .= '?' . $send_to_parts['query'];
                    }
                }
                
                // remove edit submitted form name and value from send to query string, because we don't want to send the user to edit the submitted form
                $send_to = str_replace('&edit_submitted_form=true', '', $send_to);
                $send_to = str_replace('?edit_submitted_form=true', '', $send_to);
                
                header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($send_to));
                
            // else forward user to view submitted forms in backend
            } else {
                $liveform->remove_form();
                $liveform_view_submitted_forms = new liveform('view_submitted_forms');
                
                // add notice that submitted form has been saved
                $liveform_view_submitted_forms->add_notice($message);
                
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_submitted_forms.php');
            }
            
            
            
        // else an error does exist, so forward user back to edit submitted form
        } else {
            // The page's own edit form (form_item_view_page_id) shows the
            // errors where it was posted from; this screen shows them itself
            // and keeps the page to go back to.
            if (!empty($_POST['form_item_view_page_id']) && (isset($_POST['send_to']) == TRUE) && ($_POST['send_to'] != '')) {
                header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_POST['send_to'] ?? '')));
                
            // else forward user to edit submitted form in backend
            } else {
                pg_submitted_form_mark_chosen_files($liveform, $fields, $_FILES);

                $send_to = pg_safe_redirect_path(pg_send_to_url(''), '/__none__');
                if ($send_to === '/__none__') $send_to = '';
                // A loaded revision stays loaded, so its save is still a restore.
                $restore_no = (is_scalar($_POST['restore_revision'] ?? null) && (int) $_POST['restore_revision'] > 0) ? (int) $_POST['restore_revision'] : 0;
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_submitted_form.php?id=' . (int) $_POST['id'] . (($send_to !== '') ? '&send_to=' . urlencode($send_to) : '') . (($restore_no > 0) ? '&revision=' . $restore_no : ''));
            }
        }
    }
}