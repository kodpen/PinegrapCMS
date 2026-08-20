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

// Creates a new submitted form from the back-end.
//
// This screen has two phases, which are driven by the page_id of the custom form:
//
//   add_submitted_form.php               -> phase 1, pick the custom form
//   add_submitted_form.php?page_id=42    -> phase 2, fill in that custom form's fields
//
// The field rendering is modeled on edit_submitted_form.php and the database
// inserts are modeled on import_submitted_forms.php and custom_form.php.

include('init.php');
$user = validate_user();
validate_forms_access($user);

include_once('liveform.class.php');

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
            AND (page.page_type = 'custom form')");

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
            WHERE page.page_type = 'custom form'
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
                'cancel'=>array('enable'=>'true','url'=>'view_submitted_forms.php'),

                'breadcrumb' => array(array('label' => lang('My Submitted Forms'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_submitted_forms.php'), array('label' => lang('Add Submitted Form'))),
            )
        ) . '
                <div class="row">
                <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                    <div class="row mb-2  flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
                            <h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Manually create a submitted form for any existing custom form.') . '" title="' . lang('Add Submitted Form') . '">[' . lang('new submitted form') . ']</h2>
                        </div>
                    </div>
                    <form name="form" action="add_submitted_form.php" method="get" class="product_form">
                        <div class="row">
                            <div class="col-12">
                                <div class="card my-4">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Custom Form') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label class="form-label" for="page_id">' . lang('Select the Custom Form') . '</label>
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'page_id', 'id'=>'page_id', 'class'=>'form-select', 'options'=>$custom_form_options)) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                            <div class="container">
                                <div class=" btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="continue_button" name="continue_button" value="Continue" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Loading') ) . '" ><span class="material-icons me-2">arrow_forward</span><span class="btn-text" >' . lang(array('string'=>'Continue') ) . '</span></button>
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

        // get all fields for this form
        $query = "SELECT
                    id,
                    label,
                    type,
                    required,
                    information,
                    default_value,
                    size,
                    maxlength,
                    wysiwyg,
                    `rows`, # Backticks for reserved word.
                    cols,
                    multiple,
                    spacing_above,
                    spacing_below,
                    contact_field,
                    office_use_only
                 FROM form_fields
                 WHERE page_id = '" . escape($page_id) . "'
                 ORDER BY sort_order";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        $fields = array();

        while ($row = mysqli_fetch_assoc($result)) {
            $fields[] = $row;
        }

        $wysiwyg_fields = array();

        $output_fields = '';

        // Prepare to keep track if whether there are file upload fields or not,
        // in order to know later if we need to output enctype for form.
        $file_upload_exists = false;

        foreach ($fields as $field) {
            $field_required = '';

            // If this is not an office use only field, or the user has access to edit office use only fields from this screen, then output the field
            if (($field['office_use_only'] == 0) || ($office_use_only_access == true)) {
                // if field is for office use only, then prepare to apply office use only style to row
                if ($field['office_use_only'] == 1) {
                    $row_class = 'text-muted';

                // else field is not for office use only, so don't prepare any special styling
                } else {
                    $row_class = '';
                }

                if ($field['size'] == 0) {
                    $field['size'] = '';
                }

                if ($field['maxlength'] == 0) {
                    $field['maxlength'] = '';
                }

                if ($field['rows'] == 0) {
                    $field['rows'] = '';
                }

                if ($field['cols'] == 0) {
                    $field['cols'] = '';
                }

                if ($field['label'] && $field['required']) {
                    $field['label'] .= '*';
                }

                if ($field['required']) {
                    $field_required = '<div class="invalid-feedback">' . lang('Required Area') . '</div>';
                }

                // if field has options, get options
                if (($field['type'] == 'pick list') || ($field['type'] == 'radio button') || ($field['type'] == 'check box')) {
                    $query = "SELECT
                                id,
                                label,
                                value,
                                default_selected
                             FROM form_field_options
                             WHERE form_field_id = '" . $field['id'] . "'
                             ORDER BY sort_order";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    $options = array();

                    while ($row = mysqli_fetch_assoc($result)) {
                        $options[] = $row;
                    }
                }

                // if field should have spacing above, add spacing
                if ($field['spacing_above']) {
                    $output_fields .=
                        '<div class="py-2 ' . $row_class . '"></div>';
                }

                switch ($field['type']) {
                    case 'text box':
                    case 'email address':
                        $output_fields .=
                            '<div class="col-12 col-md-6 col-lg-4 my-2 ' . $row_class . '">
                                <label class="form-label">' . $field['label'] . '</label>
                                ' . $liveform->output_field(array('type'=>'text', 'name'=>$field['id'], 'value'=>$field['default_value'], 'size'=>$field['size'], 'class'=>'form-control', 'maxlength'=>$field['maxlength'])) . '
                                ' . $field_required . '
                            </div>';
                        break;

                    case 'text area':
                        $style = '';

                        // if field is wysiwyg
                        if ($field['wysiwyg'] == 1) {
                            // add field to wysiwyg fields array, so that we can prepare JavaScript later
                            $wysiwyg_fields[] = $field['id'];

                            // if rows was not set, then set default rows so that WYSIWYG editor appears correctly
                            if (!$field['rows']) {
                                $field['rows'] = 15;
                            }

                            // if cols was not set, then set default width so that WYSIWYG editor appears correctly
                            if (!$field['cols']) {
                                $style = 'width: 100%';
                            }
                        }

                        $output_fields .=
                            '<div class="col-12 my-2 ' . $row_class . '">
                                <label class="form-label">' . $field['label'] . '</label>
                                ' . $liveform->output_field(array('type'=>'textarea', 'name'=>$field['id'], 'id'=>$field['id'], 'value'=>$field['default_value'], 'maxlength'=>$field['maxlength'], 'class'=>'form-control', 'rows'=>$field['rows'], 'cols'=>$field['cols'], 'style'=>$style)) . '
                                ' . $field_required . '
                            </div>';
                        break;

                    case 'pick list':
                        if ($field['multiple'] == 1) {
                            $name = $field['id'] . '[]';
                            $multiple = 'multiple';
                        } else {
                            $name = $field['id'];
                            $multiple = '';
                        }

                        $pick_list_options = array();

                        foreach ($options as $option) {
                            $pick_list_options[$option['label']] =
                                array(
                                    'value' => $option['value'],
                                    'default_selected' => $option['default_selected']
                                );
                        }

                        $output_fields .=
                            '<div class="col-12 col-md-6 col-lg-4 my-2 ' . $row_class . '">
                                <label class="form-label">' . $field['label'] . '</label>
                                ' . $liveform->output_field(array('type'=>'select', 'name'=>$name, 'value'=>$field['default_value'], 'options'=>$pick_list_options, 'class'=>'form-select', 'size'=>$field['size'], 'multiple'=>$multiple)) . '
                                ' . $field_required . '
                            </div>';

                        break;

                    case 'radio button':
                        $output_options = '';

                        foreach ($options as $option) {
                            // if this radio button should be selected by default, prepare to select by default
                            if ($option['value'] == $field['default_value']) {
                                $checked = 'checked';
                            } else {
                                $checked = '';
                            }

                            $output_options .= '
                            <div class="form-check">
                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>$field['id'], 'id'=>'software_option_' . $option['id'], 'value'=>$option['value'], 'checked'=>$checked, 'class'=>'form-check-input')) . '
                                <label for="software_option_' . $option['id'] . '"> ' . h($option['label']) . '</label>
                            </div>';
                        }

                        $output_fields .=
                            '<div class="col-12 my-2 ' . $row_class . '">
                                <label class="form-label">' . $field['label'] . '</label>
                                ' . $output_options . '
                            </div>';

                        break;

                    case 'check box':
                        $output_options = '';

                        // If there is more than one option in this check box group, then the field
                        // name has to be an array, otherwise only the last checked option would be
                        // submitted.  This matches the canonical form rendering in functions.php.
                        if (count($options) > 1) {
                            $name = $field['id'] . '[]';
                        } else {
                            $name = $field['id'];
                        }

                        foreach ($options as $option) {
                            // if this checkbox should be selected by default, prepare to select by default
                            if (($option['default_selected'] == 1) || ($option['value'] == $field['default_value'])) {
                                $checked = 'checked';
                            } else {
                                $checked = '';
                            }

                            $output_options .= '
                            <div class="form-check">
                                ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>$name, 'id'=>'software_option_' . $option['id'], 'value'=>$option['value'], 'checked'=>$checked, 'class'=>'form-check-input')) . '
                                <label for="software_option_' . $option['id'] . '"> ' . h($option['label']) . '</label>
                            </div>';
                        }

                        // Note that edit_submitted_form.php outputs a hidden field here, so that the
                        // field always appears in the post data.  That is not needed when a submitted
                        // form is being created, because there is no existing data to replace.  The
                        // set of fields that gets saved is determined by the office use only gate.

                        $output_fields .=
                            '<div class="col-12 my-2 ' . $row_class . '">
                                <label class="form-label">' . $field['label'] . '</label>
                                ' . $output_options . '
                            </div>';

                        break;

                    case 'file upload':
                        $output_fields .=
                        '<div class="col-12 border py-2 my-2 ' . $row_class . '">
                            <label class="form-label">' . $field['label'] . '</label>
                            <div class="input-group d-flex flex-row flex-wrap">
                                ' . $liveform->output_field(array('type'=>'file', 'name'=>$field['id'], 'class'=>'form-control', 'style'=>'min-width:200px;', 'size'=>$field['size'] )) . '
                            </div>
                            ' . $field_required . '
                        </div>';

                        $file_upload_exists = true;

                        break;

                    case 'date':
                        $output_fields .=
                            '<div class="col-12 col-md-6 col-lg-4 my-2 ' . $row_class . '">
                                <label class="form-label">' . $field['label'] . '</label>
                                ' . $liveform->output_field(array('type'=>'text', 'id' => $field['id'], 'name'=>$field['id'], 'value'=>$field['default_value'], 'size'=>$field['size'], 'class'=>'form-control', 'maxlength'=>'10')) . '
                                ' . $field_required . '
                                ' . get_date_picker_format() . '
                                    <script>
                                        $("#' . $field['id'] . '").datepicker(datetimepicker_options);
                                    </script>
                            </div>';
                        break;

                    case 'date and time':
                        $output_fields .=
                            '<div class="col-12 col-md-6 col-lg-4 my-2 ' . $row_class . '">
                                <label class="form-label">' . $field['label'] . '</label>
                                ' . $liveform->output_field(array('type'=>'text', 'id' => $field['id'], 'name'=>$field['id'], 'value'=>$field['default_value'], 'size'=>$field['size'], 'class'=>'form-control', 'maxlength'=>'22')) . '
                                ' . $field_required . '
                                ' . get_date_time_picker_format() . '
                                    <script>
                                        $("#' . $field['id'] . '").datetimepicker(datetimepicker_options);
                                    </script>
                            </div>';
                        break;

                    case 'information':
                        $output_fields .=
                            '<div class="py-3 ' . $row_class . '">' . $field['information'] . '</div>';
                        break;

                    case 'time':
                        $output_fields .=
                            '<div class="col-12 col-md-6 col-lg-4 my-2 ' . $row_class . '">
                                <label class="form-label">' . $field['label'] . '</label>
                                ' . $liveform->output_field(array('type'=>'text', 'id' => $field['id'], 'name'=>$field['id'], 'value'=>$field['default_value'], 'size'=>$field['size'], 'class'=>'form-control', 'maxlength'=>'11')) . '
                                ' . $field_required . '
                                <div class="form-text text-end">Format: h:mm AM/PM</div>
                                ' . get_time_picker_format() . '
                                    <script>
                                        $("#' . $field['id'] . '").timepicker(timepicker_options);
                                    </script>
                            </div>';
                        break;

                    default:
                        $output_fields .=
                            '<div class="col-12 my-2 ' . $row_class . '">
                                <label class="form-label">' . $field['label'] . '</label>
                            </div>';

                        break;
                }

                // if field should have spacing below, add spacing
                if ($field['spacing_below']) {
                    $output_fields .=
                        '<div class="py-2 ' . $row_class . '"></div>';
                }
            }
        }

        $output_wysiwyg_javascript = '';

        // if there is at least one wysiwyg field, prepare wysiwyg fields
        if ($wysiwyg_fields) {
            $output_wysiwyg_javascript = get_wysiwyg_editor_code($wysiwyg_fields);
        }

        // Assume that we don't need to set enctype for HTML form until we find out otherwise.
        $output_enctype = '';

        // If a file upload field exists in the form, then prepare to set enctype for HTML form
        if ($file_upload_exists == true) {
            $output_enctype = ' enctype="multipart/form-data"';
        }

        echo
        pg_page_shell(
            array(
                'title'=> lang('Add Submitted Form'),
                'extra classes'=>'form',
                'icon'=>'form',
                'heading'=>lang('Add Submitted Form'),
                'cancel'=>array('enable'=>'true','url'=>'view_submitted_forms.php'),
                'breadcrumb' => array(
                    array('label' => lang('My Submitted Forms'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_submitted_forms.php'),
                    array('label' => lang('Add Submitted Form')),
                ),
            )
        ) . '
        <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
                ' . $output_wysiwyg_javascript . '
            <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    ' . $liveform->get_warnings() . '
                    ' . $liveform->output_notices() . '
                    <div class="row mb-2  flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
                            <div class="row mb-2">
                                <div class="col-12 col-md">
                                    <h2 class="d-inline-block text-break" data-bs-content="' . lang('Manually create a submitted form for this custom form. Office use only fields are also visible.') . '" title="' . lang('Add Submitted Form') . '">[' . h($form_name) . ']</h2>
                                    <p class="p-0 m-0"><a class="link-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_submitted_form.php">' . lang('Select a different custom form') . '</a></p>
                                </div>
                            </div>
                        </div>
                        <form' . $output_enctype . ' name="form" action="add_submitted_form.php" method="post">
                            ' . get_token_field() . '
                            <input type="hidden" name="page_id" value="' . h($page_id) . '" />
                            <div class="row">
                                <div class="col-12">
                                    <div class="card my-4 ">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('Form Properties') . '
                                        </div>
                                        <div class="card-body text-center text-md-start">
                                            <div class="row">
                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                    <div class="form-text">' . lang('Custom Form') . '</div>
                                                    <h5>' . h($form_name) . '</h5>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                    <div class="form-text">' . lang('Notifications') . '</div>
                                                    <div class="form-check form-switch">
                                                        ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'send_notifications', 'id'=>'send_notifications', 'value'=>'1', 'class'=>'form-check-input')) . '
                                                        <label class="form-check-label" for="send_notifications">' . lang('Send notification e-mails') . '</label>
                                                    </div>
                                                    <div class="form-text">' . lang('Off by default. When on, the same e-mails that a visitor submission would send are sent.') . '</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('Form Data') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                ' . $output_fields . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                                <div class="container">
                                    <div class=" btn-group flex-wrap justify-content-center">
                                        <button type="submit" name="save_for_later_button" value="Save for Later" class="btn my-1  btn-secondary " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save_as</span><span class="btn-text" >' . lang(array('string'=>'Save for Later') ) . '</span></button>
                                        <button type="submit" name="complete_button" value="Complete" class="btn my-1  btn-success" data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang(array('string'=>'Save and Complete') ) . '</span></button>
                                    </div>
                                </div>
                            </nav>
                        </form>
                    </div>
                </div>
            </div>
        </main>
        ' . output_footer();

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
                $error_message = lang(array('string'=>'{var:1} is required.','vars'=>$field['label']));
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

        // if field has date type and there is not already an error for this field and user entered value for field and submitted date is invalid, prepare error
        if (($field['type'] == 'date') && ($liveform->check_field_error($field['id']) == false) && ($liveform->get_field_value($field['id']) != '') && (validate_date($liveform->get_field_value($field['id'])) == false)) {
            $liveform->mark_error($field['id'], lang(array('string'=>'Please enter a valid date for {var:1}','vars'=>$field['label'])) );
        }

        // if field has date & time type and there is not already an error for this field and user entered value for field and submitted date & time is invalid, prepare error
        if (($field['type'] == 'date and time') && ($liveform->check_field_error($field['id']) == false) && ($liveform->get_field_value($field['id']) != '') && (validate_date_and_time($liveform->get_field_value($field['id'])) == false)) {
            $liveform->mark_error($field['id'], lang(array('string'=>'Please enter a valid date & time for {var:1}','vars'=>$field['label'])) );
        }

        // if field has email address type and there is not already an error for this field and user entered value for field and submitted e-mail address is invalid, prepare error
        if (($field['type'] == 'email address') && ($liveform->check_field_error($field['id']) == false) && ($liveform->get_field_value($field['id']) != '') && (validate_email_address($liveform->get_field_value($field['id'])) == false)) {
            $liveform->mark_error($field['id'], lang(array('string'=>'Please enter a valid e-mail address for {var:1}','vars'=>$field['label'])) );
        }

        // if field has time type and there is not already an error for this field and user entered value for field and submitted time is invalid, prepare error
        if (($field['type'] == 'time') && ($liveform->check_field_error($field['id']) == false) && ($liveform->get_field_value($field['id']) != '') && (validate_time($liveform->get_field_value($field['id'])) == false)) {
            $liveform->mark_error($field['id'], lang(array('string'=>'Please enter a valid time for {var:1}','vars'=>$field['label'])) );
        }

        // If this field is a title field and there is not already an error for this field,
        // and the user entered a value for this field, and pretty URLs are enabled,
        // then check if address name is already in use.
        if (
            ($field['rss_field'] == 'title')
            && ($liveform->check_field_error($field['id']) == false)
            && ($liveform->get_field_value($field['id']) != '')
            && ($pretty_urls == true)
        ) {
            $address_name = create_address_name($liveform->get_field_value($field['id']));

            // If that address name is already in use, then output error.
            if (db_value("SELECT COUNT(*) FROM forms WHERE (page_id = '" . escape($page_id) . "') AND (address_name = '" . escape($address_name) . "')") > 0) {
                $liveform->mark_error($field['id'], lang(array('string'=>'That {var:1} is already in use. Please enter a different one.','vars'=>$field['label'])) );
            }
        }
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
                '" . escape($page_id) . "',
                '" . $new_complete . "',
                '" . $user['id'] . "',
                '" . escape($reference_code) . "',
                UNIX_TIMESTAMP(),
                '" . $user['id'] . "',
                UNIX_TIMESTAMP())";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

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
                    $file_name = prepare_file_name($_FILES[$field['id']]['name']);

                    // Check if file name is already in use and change it if necessary.
                    $file_name = get_unique_name(array(
                        'name' => $file_name,
                        'type' => 'file'));

                    $file_size = $_FILES[$field['id']]['size'];
                    $file_temp_name = $_FILES[$field['id']]['tmp_name'];

                    // Get the position of the last period in order to get the extension.
                    $position_of_last_period = mb_strrpos($file_name, '.');

                    $file_extension = '';

                    // If there is an extension then remember it.
                    if ($position_of_last_period !== false) {
                        $file_extension = mb_substr($file_name, $position_of_last_period + 1);
                    }

                    // create file
                    copy($file_temp_name, FILE_DIRECTORY_PATH . '/' . $file_name);

                    // create file record in database
                    $query = "INSERT INTO files (
                                name,
                                folder,
                                type,
                                size,
                                user,
                                design,
                                attachment,
                                timestamp)
                             VALUES (
                                '" . escape($file_name) . "',
                                '" . escape($field['upload_folder_id']) . "',
                                '" . escape($file_extension) . "',
                                '" . escape($file_size) . "',
                                '" . $user['id'] . "',
                                '0',
                                '1',
                                UNIX_TIMESTAMP())";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    $file_id = mysqli_insert_id(db::$con);

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

            // Otherwise the field is not a file upload type so save data in a different way.
            } else {
                // assume that the form data type is standard until we find out otherwise
                $form_data_type = 'standard';

                // if the form field's type is date, date and time, or time, then set form data type to the form field type
                if (
                    ($field['type'] == 'date')
                    || ($field['type'] == 'date and time')
                    || ($field['type'] == 'time')
                ) {
                    $form_data_type = $field['type'];

                // else if the form field is a wysiwyg text area, then set type to html and prepare data for input
                } elseif (($field['type'] == 'text area') && ($field['wysiwyg'] == 1)) {
                    $form_data_type = 'html';

                    $liveform->assign_field_value($field['id'], prepare_rich_text_editor_content_for_input($liveform->get_field_value($field['id'])));
                }

                // if this field has multiple values (i.e. check box group or pick list)
                if (is_array($liveform->get_field_value($field['id'])) == true) {
                    foreach ($liveform->get_field_value($field['id']) as $value) {
                        // store form data
                        $query = "INSERT INTO form_data (
                                    form_id,
                                    form_field_id,
                                    data,
                                    name,
                                    type)
                                 VALUES (
                                    '" . $form_id . "',
                                    '" . $field['id'] . "',
                                    '" . escape(prepare_form_data_for_input($value, $field['type'])) . "',
                                    '" . escape($field['name']) . "',
                                    '$form_data_type')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }

                // else this field does not have multiple values
                } else {
                    // store form data
                    $query = "INSERT INTO form_data (
                                form_id,
                                form_field_id,
                                data,
                                name,
                                type)
                             VALUES (
                                '" . $form_id . "',
                                '" . $field['id'] . "',
                                '" . escape(prepare_form_data_for_input($liveform->get_field_value($field['id']), $field['type'])) . "',
                                '" . escape($field['name']) . "',
                                '$form_data_type')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
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
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_submitted_form.php?page_id=' . urlencode($page_id));
    }
}
?>