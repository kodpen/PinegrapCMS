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

// Creates a new submitted form from the back-end.
//
// This screen has two phases, which are driven by the page_id of the custom form:
//
//   add_submitted_form.php               -> phase 1, pick the custom form
//   add_submitted_form.php?page_id=42    -> phase 2, fill in that custom form's fields
//
// The fields, their type checks and the stored values are shared with
// edit_submitted_form.php (includes/forms/submitted_form_form.php); the
// database inserts are modeled on import_submitted_forms.php and custom_form.php.

include('init.php');
$user = validate_user();
validate_forms_access($user);

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/submitted_form_form.php');

// The custom form's page is one value; one sent as an array (page_id[]=x)
// counts as not given: it ended in an error page.
pg_request_single_values(array('page_id'));

// Get the custom form's page id from the post (saving) or the query string (screen).
$page_id = '';

if (isset($_POST['page_id']) == true) {
    $page_id = trim($_POST['page_id']);
} elseif (isset($_GET['page_id']) == true) {
    $page_id = trim($_GET['page_id']);
}

$folder_id = '';
$form_name = '';

// If a custom form was chosen, then make sure it exists and that the user has access to it.
if ($page_id != '') {
    $custom_form = db_item(
        "SELECT
            page.page_id,
            page.page_name,
            page.page_folder as folder_id,
            custom_form_pages.form_name
        FROM page
        LEFT JOIN custom_form_pages ON page.page_id = custom_form_pages.page_id
        WHERE
            (page.page_id = '" . escape($page_id) . "')
            AND " . pg_form_page_sql('page'));

    // If a custom form could not be found for that page id, then output an error.
    if (!$custom_form) {
        log_activity(lang('access denied to add a submitted form because the custom form could not be found'), $_SESSION['sessionusername']);
        output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $folder_id = $custom_form['folder_id'];

    // If the user does not have access to modify the folder that the custom form is in,
    // then output an error.  We validate this here, in addition to when we build the pick
    // list, because the custom form can also be passed in the query string or the post.
    if (check_edit_access($folder_id) == false) {
        log_activity(lang('access denied to add a submitted form because user does not have access to modify folder that custom form is in'), $_SESSION['sessionusername']);
        output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    if ($custom_form['form_name']) {
        $form_name = $custom_form['form_name'];
    } else {
        $form_name = $custom_form['page_name'];
    }
}

// The live form is indexed by the custom form's page id, because a submitted form id
// does not exist yet, and because we do not want values that were entered for one
// custom form to bleed into another custom form.
$liveform = new liveform('add_submitted_form', $page_id);

$pretty_urls = false;

if ($page_id != '') {
    $pretty_urls = check_if_pretty_urls_are_enabled($page_id);
}

// The user got this far, so validate_forms_access() has already confirmed that the user
// is a manager or above, or has access to manage forms, and check_edit_access() has
// confirmed access to the custom form's folder.  This mirrors the office use only gate in
// edit_submitted_form.php, and is used in both the render loop and the save loop, so that
// the exact same set of fields is displayed and saved.
$office_use_only_access = (
    ($user['role'] < 3)
    || (($page_id != '') && (check_edit_access($folder_id) == true) && ($user['manage_forms'] == TRUE))
);

if (!$_POST) {

    // If a custom form has not been chosen yet, then output the custom form pick list.
    if ($page_id == '') {

        // If the user submitted the pick list without choosing a custom form, then prepare an error.
        if (isset($_GET['page_id']) == true) {
            $liveform->mark_error('page_id', lang('Custom Form is required.'));
        }

        // get custom forms for pick list
        $custom_form_options = array();
        $custom_form_options['-' . lang('Select') . '-'] = '';

        $query =
            "SELECT
               page.page_id,
               page.page_name,
               page.page_folder,
               custom_form_pages.form_name
            FROM page
            LEFT JOIN custom_form_pages ON page.page_id = custom_form_pages.page_id
            WHERE " . pg_form_page_sql('page') . "
            ORDER BY custom_form_pages.form_name";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            // if user has access to this custom form, add custom form to pick list
            if (check_edit_access($row['page_folder']) == true) {
                if ($row['form_name']) {
                    $name = $row['form_name'];
                } else {
                    $name = $row['page_name'];
                }

                $custom_form_options[$name] = $row['page_id'];
            }
        }

        print
        pg_page_shell(
            array(
                'title'=> lang('Add Submitted Form'),
                'extra classes'=>'form',
                'icon'=>'form',
                'heading'=>lang('Add Submitted Form'),
                'heading_description' => lang('Manually create a submitted form for any existing custom form.'),
                'cancel'=>array('enable'=>'true','url'=>'view_submitted_forms.php'),

                'breadcrumb' => array(array('label' => lang('Forms'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_submitted_forms.php'), array('label' => lang('Add Submitted Form'))),
            )
        ) . '
<main id="content" class="container-fluid">
                <div class="row">
                <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                    
                    <form name="form" action="add_submitted_form.php" method="get" class="product_form">
                        <div class="row">
                            <div class="col-12">
                                <div class="card my-7">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Custom Form') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label class="form-label" for="page_id">' . lang('Select the Custom Form') . '</label>
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'page_id', 'id'=>'page_id', 'class'=>'form-control', 'options'=>$custom_form_options)) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class=" btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="continue_button" name="continue_button" value="Continue" class="btn my-1  btn-solid theme-success " ><span class="material-icons me-3">arrow_forward</span><span class="pg-btn-label" >' . lang(array('string'=>'Continue') ) . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        
</main>' .
        output_footer();

        $liveform->unmark_errors();
        $liveform->clear_notices();

    // Otherwise a custom form was chosen, so output that custom form's fields.
    } else {

        $form_markup = pg_submitted_form_fields_markup($liveform, $user, $page_id, $office_use_only_access);

        echo
        pg_page_shell(
            array(
                'title'=> lang('Add Submitted Form'),
                'extra classes'=>'form',
                'icon'=>'form',
                'heading'=>lang('Add Submitted Form'),
                'cancel'=>array('enable'=>'true','url'=>'view_submitted_forms.php'),
                'breadcrumb' => array(
                    array('label' => lang('Forms'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_submitted_forms.php'),
                    array('label' => lang('Add Submitted Form')),
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
                                    <h2 class="d-inline-block text-break" data-bs-content="' . lang('Manually create a submitted form for this custom form. Office use only fields are also visible.') . '" title="' . lang('Add Submitted Form') . '">[' . h($form_name) . ']</h2>
                                    <p class="p-0 m-0"><a class="pg-link-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_submitted_form.php">' . lang('Select a different custom form') . '</a></p>
                                </div>
                            </div>
                        </div>
                        <form' . $form_markup['enctype'] . ' name="form" action="add_submitted_form.php" method="post">
                            ' . get_token_field() . '
                            <input type="hidden" name="page_id" value="' . h($page_id) . '" />
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
                                                    <div class="form-text">' . lang('Notifications') . '</div>
                                                    <div class="form-field">
                                                        <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'send_notifications', 'id'=>'send_notifications', 'value'=>'1', 'class'=>'no-bs')) . '</div>
                                                        <label class="" for="send_notifications">' . lang('Send notification e-mails') . '</label>
                                                    </div>
                                                    <div class="form-text">' . lang('Off by default. When on, the same e-mails that a visitor submission would send are sent.') . '</div>
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
                                        <button type="submit" name="save_for_later_button" value="Save for Later" class="btn my-1  btn-solid theme-secondary "><span class="material-icons me-3">save_as</span><span class="pg-btn-label" >' . lang(array('string'=>'Save for Later') ) . '</span></button>
                                        <button type="submit" name="complete_button" value="Complete" class="btn my-1  btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save and Complete') ) . '</span></button>
                                    </div>
                                </div>
                            </nav>
                        </form>
                    </div>
                </div>
            </div>
        
    ' . pg_ai_write_assets() . '
</main>' . output_footer();

        $liveform->unmark_errors();
        $liveform->clear_notices();
    }

// Otherwise the form was submitted, so create the submitted form.
} else {
    validate_token_field();

    // If a custom form was not posted, then send the user back to choose one.
    if ($page_id == '') {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_submitted_form.php');
        exit();
    }

    $liveform->add_fields_to_session();

    // get all fields for this form
    $fields = db_items(
        "SELECT
            id,
            name,
            rss_field,
            label,
            type,
            wysiwyg,
            multiple,
            contact_field,
            required,
            office_use_only,
            upload_folder_id
         FROM form_fields
         WHERE
            (page_id = '" . escape($page_id) . "')
            AND (type != 'information')
         ORDER BY sort_order");

    // Loop through fields in order to validate them.
    foreach ($fields as $field) {
        // If this is an office use only field that was not displayed, then skip it.
        if (($field['office_use_only'] == 1) && ($office_use_only_access == false)) {
            continue;
        }

        // If field is required and the user clicked the complete button, then require the field.
        // Required fields are not enforced for the save for later button, so that a partially
        // filled in submitted form can still be stored.
        if ($field['required'] and $liveform->field_in_session('complete_button')) {
            $error_message = '';

            if ($field['label']) {
                $error_message = lang(array('string'=>'{var:1} is required.','vars'=>h($field['label'])));
            }

            // If field is a file upload type then determine if field should be required, in a certain way.
            if ($field['type'] == 'file upload') {
                if ((isset($_FILES[$field['id']]) == false) || ($_FILES[$field['id']]['name'] == '')) {
                    $liveform->mark_error($field['id'], $error_message);
                }

            // Otherwise field is not a file upload type, so determine if field should be required, in a different way.
            } else {
                $liveform->validate_required_field($field['id'], $error_message);
            }
        }

        // A signature that arrived as anything other than a PNG data URL is
        // refused here, the same as on the visitor's form.
        if (
            ($field['type'] == 'signature')
            && function_exists('pg_signature_png_from_data_url')
            && ($liveform->check_field_error($field['id']) == false)
            && ($liveform->get_field_value($field['id']) != '')
            && (pg_signature_png_from_data_url($liveform->get_field_value($field['id'])) === false)
        ) {
            $liveform->mark_error($field['id'], lang('The signature could not be read. Please clear the box and sign again.'));
        }

        pg_submitted_form_check_field($liveform, $field, $page_id, $pretty_urls);
    }

    // if an error does not exist
    if ($liveform->check_form_errors() == false) {

        // If the save for later button was clicked, then mark the form as incomplete.
        if ($liveform->field_in_session('save_for_later_button')) {
            $new_complete = 0;

        // Otherwise the complete button was clicked, so mark the form as complete.
        } else {
            $new_complete = 1;
        }

        $reference_code = generate_form_reference_code();

        // Create the submitted form.  The user that is creating the submitted form is stored
        // as the submitter, which is what import_submitted_forms.php does as well.  Visitor
        // specific columns (ip address, http referer, tracking code, affiliate code) are not
        // stored, because this submitted form was not created by a visitor.
        $query =
            "INSERT INTO forms (
                page_id,
                complete,
                user_id,
                reference_code,
                submitted_timestamp,
                last_modified_user_id,
                last_modified_timestamp)
            VALUES (
                ?,
                ?,
                ?,
                ?,
                UNIX_TIMESTAMP(),
                ?,
                UNIX_TIMESTAMP())";
        $result = db_q($query, array((string) $page_id, (string) $new_complete, (string) $user['id'], (string) $reference_code, (string) $user['id'])) or output_error('Query failed.');

        $form_id = mysqli_insert_id(db::$con);

        // Loop through all fields in order to save data for each one.
        foreach ($fields as $field) {
            // If this is an office use only field that was not displayed, then skip it.
            if (($field['office_use_only'] == 1) && ($office_use_only_access == false)) {
                continue;
            }

            // If field is a file upload type then save data in a certain way.
            if ($field['type'] == 'file upload') {
                // If a file was uploaded, then deal with that.
                if ((isset($_FILES[$field['id']]) == true) && ($_FILES[$field['id']]['name'] != '')) {
                    $file_id = pg_submitted_form_store_upload($field, $user['id']);

                    db(
                        "INSERT INTO form_data (
                            form_id,
                            form_field_id,
                            file_id,
                            name)
                        VALUES (
                            '" . $form_id . "',
                            '" . $field['id'] . "',
                            '" . $file_id . "',
                            '" . escape($field['name']) . "')");

                // Otherwise a file was not uploaded, so insert a blank form data record,
                // which is what happens when a visitor submits a form without a file.
                } else {
                    db(
                        "INSERT INTO form_data (
                            form_id,
                            form_field_id,
                            name)
                        VALUES (
                            '" . $form_id . "',
                            '" . $field['id'] . "',
                            '" . escape($field['name']) . "')");
                }

            // A signature is written through its own path: the drawing becomes a
            // file, form_data points at it, and the record that gives it meaning
            // is written beside it. The document is read again here because the
            // field list above drops information fields, and that is where a
            // contract is written.
            } else if (($field['type'] == 'signature') && function_exists('pg_signature_ready') && pg_signature_ready()) {
                $signature_png = pg_signature_png_from_data_url($liveform->get_field_value($field['id']));
                $signature_file_id = false;

                if ($signature_png !== false) {
                    $signature_document = db_items(
                        "SELECT id, name, label, type, required, information
                        FROM form_fields
                        WHERE page_id = '" . escape($page_id) . "'
                        ORDER BY sort_order ASC");

                    $signature_values = array();

                    foreach ($fields as $signature_source) {
                        if ($signature_source['type'] == 'signature') {
                            continue;
                        }

                        $signature_values[$signature_source['id']] = $liveform->get_field_value($signature_source['id']);
                    }

                    $signature_file_id = pg_signature_store(
                        $form_id,
                        $field['id'],
                        $signature_png,
                        array(
                            'page_id' => $page_id,
                            'document_hash' => pg_signature_document_hash($page_id, $signature_document, $signature_values, $field['label']),
                            'consent_text' => $field['label'],
                            'strokes' => pg_signature_clean_strokes(isset($_POST[$field['id'] . '_strokes']) ? $_POST[$field['id'] . '_strokes'] : ''),
                        ));
                }

                db(
                    "INSERT INTO form_data (
                        form_id,
                        form_field_id,
                        file_id,
                        name)
                    VALUES (
                        '" . $form_id . "',
                        '" . $field['id'] . "',
                        '" . (($signature_file_id === false) ? '0' : (int) $signature_file_id) . "',
                        '" . escape($field['name']) . "')");

            // Otherwise the field is not a file upload type so save data in a different way.
            } else {
                pg_submitted_form_save_value($liveform, $form_id, $field);
            }
        }

        // If pretty URLs are enabled, then update address name.
        if ($pretty_urls == true) {
            update_submitted_form_address_name($form_id);
        }

        // If the user asked for notifications to be sent, and the submitted form was marked
        // as complete, then send the same notification e-mails that a visitor submission
        // would have sent.  This is off by default, because the submitted form was created
        // manually by a member of staff.
        if (
            ($new_complete == 1)
            && ($liveform->get_field_value('send_notifications') == 1)
        ) {
            pg_send_custom_form_notifications($page_id, $form_id, 0);
        }

        log_activity(lang(array('string'=>'submitted form (form name: {var:1}, reference code: {var:2}) was created','vars'=>array($form_name,$reference_code))), $_SESSION['sessionusername']);

        // If the save for later button was clicked.
        if ($liveform->field_in_session('save_for_later_button')) {
            $message = lang('The form has been created and saved for later.');

        // Otherwise the complete button was clicked.
        } else {
            $message = lang('The form has been created.');
        }

        $liveform->remove_form();

        $liveform_view_submitted_forms = new liveform('view_submitted_forms');

        // add notice that submitted form has been created
        $liveform_view_submitted_forms->add_notice($message);

        // Send the user back to the submitted forms list.  The custom form is passed along so
        // that the list focuses on it, and the end of the date range is moved to today, because
        // the list is always filtered by a date range that is remembered in the session, and a
        // stale date range would hide the submitted form that was just created.
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_submitted_forms.php?custom_form=' . urlencode($page_id) . '&stop_month=' . date('m') . '&stop_day=' . date('d') . '&stop_year=' . date('Y'));

    // else an error does exist, so forward user back to add submitted form
    } else {
        pg_submitted_form_mark_chosen_files($liveform, $fields, $_FILES);

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_submitted_form.php?page_id=' . urlencode($page_id));
    }
}
?>