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
    WHERE forms.id = '" . escape($_REQUEST['id']) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

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
if ($_POST['form_item_view_page_id']) {
    $query =
        "SELECT 
            custom_form_page_id,
            submitted_form_editable_by_registered_user,
            submitted_form_editable_by_submitter,
            hook_code
        FROM form_item_view_pages
        WHERE
            (page_id = '" . escape($_POST['form_item_view_page_id'] ?? '') . "')
            AND (collection = 'a')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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
    if ($_POST['form_item_view_page_id']) {
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
$liveform = new liveform('edit_submitted_form', $_REQUEST['id']);

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
        WHERE forms.id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

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
            '<div class="col-12 col-md-6 col-lg-4 my-2">
                <div class="form-text">' . lang('Quiz Score') . '</div>
                <h5>' . $quiz_score . '</h5>
            </div>';
    }

    if ($complete) {
        $status = '<span class="badge bg-success fw-light">' . lang('Complete') . '</span>';
    } else {
        $status = '<span class="badge bg-secondary fw-light">' . lang('Incomplete') . '</span>';
    }

    $output_address_name = '';
    
    // If pretty URLs are enabled, then output address name.
    if ($pretty_urls == true) {
        $output_address_name = h($old_address_name);
    }
    
    $output_affiliate_code = '';

    if (AFFILIATE_PROGRAM == true) {
        $output_affiliate_code =
            '<div class="col-12 col-md-6 col-lg-4 my-2">
                <div class="form-text">' . lang('Affiliate Code') . '</div>
                <h5>' . h($affiliate_code ? $affiliate_code : '-') . '</h5>
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

    
    // if edit submitted form screen has not been submitted already, pre-populate fields with form data
    if (isset($_SESSION['software']['liveforms']['edit_submitted_form'][$_GET['id']]) == false) {
        $query = "SELECT
                    form_data.form_field_id,
                    form_data.data,
                    count(*) as number_of_values,
                    form_fields.type,
                    form_fields.wysiwyg
                 FROM form_data
                 LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
                 WHERE
                    (form_data.form_id = '" . escape($_GET['id']) . "')
                    AND (form_fields.type != 'file upload')
                 GROUP BY form_data.form_field_id";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

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
                         WHERE (form_id = '" . escape($_GET['id']) . "') AND (form_field_id = '" . $field['form_field_id'] . "')
                         ORDER BY id";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                $field['data'] = array();
                
                while ($row = mysqli_fetch_assoc($result)) {
                    $field['data'][] = $row['data'];
                }
            }
            
            // if this field is a rich-text editor field, then prepare content for output before we set field data
            if (
                ($field['type'] == 'text area')
                && ($field['wysiwyg'] == 1)
            ) {
                $field['data'] = prepare_rich_text_editor_content_for_output($field['data']);
            }
            
            $liveform->assign_field_value($field['form_field_id'], prepare_form_data_for_output($field['data'], $field['type'], $prepare_for_html = false));
        }
    }
    
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
             WHERE page_id = '$page_id'
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
        if (
            ($field['office_use_only'] == 0)
            || ($user['role'] < 3)
            || ((check_edit_access($folder_id) == true) && ($user['manage_forms'] == TRUE))
        ) {
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
                    // name has to be an array, otherwise only the last option that is checked
                    // would be submitted.  This matches the canonical form rendering that
                    // get_form_info() uses in functions.php.
                    if (count($options) > 1) {
                        $name = $field['id'] . '[]';
                        
                    // else there is just one option for this check box group
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
                    
                    // The hidden field is outputted so even if a check box is not checked, a field
                    // will be included in the post data.  This is important because we use the
                    // fields in the post data to determine which fields should be updated.
                    //
                    // The hidden field has to stay BEFORE the check boxes.  When the group's name
                    // is an array, PHP replaces this empty scalar value with the array of checked
                    // values.  If the hidden field came after the check boxes, it would replace the
                    // array with an empty string instead, and every checked value would be lost.
                    
                    $output_fields .=
                        '<div class="col-12 my-2 ' . $row_class . '">
                            <label class="form-label">' . $field['label'] . '</label>
                            <input type="hidden" name="' . $field['id'] . '" />
                            ' . $output_options . '
                        </div>';
                    
                    break;

                case 'file upload':
                    // Get file name and size for file if a file exists.
                    $file = db_item(
                        "SELECT
                            files.name,
                            files.size
                        FROM form_data
                        LEFT JOIN files on form_data.file_id = files.id
                        WHERE
                            (form_data.form_id = '" . escape($_GET['id']) . "')
                            AND (form_data.form_field_id = '" . $field['id'] . "')");

                    // If there is an existing file for this field, then output info for that.
                    if ($file['name'] != '') {
                        $output_file_info = '
                        <a class=" btn btn-light border" href="' . OUTPUT_PATH . h(encode_url_path($file['name'])) . '" target="_blank">
                            <span class="material-icons">attach_file</span>
                            ' . h($file['name']) . ' (' . convert_bytes_to_string($file['size']) . ') 
                        </a>';
                        $output_upload_label = '<div class="input-group-text">' . lang('Replace File') . ':</div>';

                        // If this field is optional, then output delete option.
                        if ($field['required'] == 0) {
                            $output_delete_option = '
                            <div class="input-group-text">' . lang('or') . '</div>
                            <div class="input-group-text">
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        ' . $liveform->output_field(array('type'=>'checkbox', 'name'=> $field['id'] . '_delete_file', 'id'=> $field['id'] . '_delete_file', 'value'=>'1', 'class'=>'form-check-input danger')) . '
                                        <label class="form-check-label text-danger" for="' . $field['id'] . '_delete_file">' . lang('Delete File') . '</label>
                                    </div>
                                </div>
                            </div>';
                        } else {
                            $output_delete_option = '';
                        }

                    // Otherwise there is not an existing file for this field, so output info for that.
                    } else {
                        $output_file_info = '';
                        $output_upload_label = '';
                        $output_delete_option = '';
                    }

                    $output_fields .=
                    '<div class="col-12 border py-2 my-2 ' . $row_class . '">
                        <label class="form-label">' . $field['label'] . '</label>
                        <div class="input-group d-flex flex-row flex-wrap">
                            ' . $output_file_info . '
                            ' . $output_upload_label . $liveform->output_field(array('type'=>'file', 'name'=>$field['id'], 'class'=>'form-control', 'style'=>'min-width:200px;', 'size'=>$field['size'] )) . $output_delete_option . '
                        </div>
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

    // If the form is incomplete then show certain buttons.
    if (!$complete) {
        $output_buttons =
            '<button type="submit" name="save_for_later_button" value="Save for Later" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save_as</span><span class="btn-text" >' . lang(array('string'=>'Save for Later') ) . '</span></button>
            <button type="submit" name="complete_button" value="Complete" class="btn my-1  btn-secondary" data-loading-content="' . lang(array('string'=>'Converting Complete') ) . '"><span class="material-icons me-2">radio_button_unchecked</span><span class="btn-text">' . lang(array('string'=>'Convert Complete') ) . '</span></button>';

    // Otherwise the form is complete, so show different buttons.
    } else {
        $output_buttons =
            '<button type="submit" name="save_button" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
            <button type="submit" name="incomplete_button" value="Incomplete" class="btn my-1  btn-warning " data-loading-content="' . lang(array('string'=>'Converting Incomplete') ) . '"><span class="material-icons me-2">radio_button_checked</span><span class="btn-text">' . lang(array('string'=>'Convert Incomplete') ) . '</span></button>';
    }
    
    echo
    pg_page_shell(
        array(
            'title'=> lang('Edit Submitted Form'),
            'extra classes'=>'form',
            'icon'=>'form',
            'heading'=>lang('Edit Submitted Form'),
            'cancel'=>array('enable'=>'true','url'=>'view_submitted_forms.php'),
            'breadcrumb' => array(
                array('label' => lang('My Submitted Forms'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_submitted_forms.php'),
                array('label' => lang('Edit Submitted Form')),
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
                                <h2 class="d-inline-block text-break" data-bs-content="' . lang('View or update this submitted form. Office use only fields are also visible.') . '" title="' . lang('Edit Submitted Form') . '">[' . h($form_name) . ']</h2>
                                <p class="p-0 m-0">' . lang('Reference Code') . ': ' . $reference_code . '</p>
                                <p class="p-0 m-0">' . lang('Submitted') . ': ' . get_relative_time(array('timestamp' => $submitted_timestamp)) . ' ' . $submitted_username . '</p>
                            </div>
                        </div>
                    </div>
                    <form' . $output_enctype . ' name="form" action="edit_submitted_form.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
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
                                                <div class="form-text">' . lang('Reference Code') . '</div>
                                                <h5>' . $reference_code . '</h5>
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <div class="form-text">' . lang('Status') . '</div>
                                                ' . $status . '
                                            </div>
                                            ' . $output_quiz_score_row . '
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <div class="form-text">' . lang('Address Name') . '</div>
                                                <h6>' . ($output_address_name ? $output_address_name : '-') . '</h6>
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <div class="form-text">' . lang('Tracking Code') . '</div>
                                                <h5>' . h($tracking_code ? $tracking_code : '-') . '</h5>
                                            </div>
                                            ' . $output_affiliate_code . '
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <div class="form-text">' . lang('Referring URL') . '</div>
                                                ' . h($http_referer_text ? '<a class="link-secondary" href="' . h(escape_url($http_referer)) . '" target="_blank">' . h($http_referer_text) . '</a>' : '-') . '
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <div class="form-text">' . h(MEMBER_ID_LABEL) . '</div>
                                                <h5>' . h($member_id ? $member_id : '-') . '</h5>
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <div class="form-text">' . lang('IP Address') . '</div>
                                                <h5>' . $output_ip_address . '</h5>
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <div class="form-text">' . lang('Submitted Date') . '</div>
                                                <h6>' . get_relative_time(array('timestamp' => $submitted_timestamp)) . ' ' . $submitted_username . '</h6>
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <div class="form-text">' . lang('Last Modified Date') . '</div>
                                                <h6>' . get_relative_time(array('timestamp' => $last_modified_timestamp)) . ' ' . $last_modified_username . '</h6>
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label class="form-text">' . lang('Form Editor') . '</label>
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'form_editor_username', 'value'=>$form_editor_username, 'class'=>'form-control', 'maxlength'=>'100')) . '
                                                <div class="form-text text-end">' . lang('enter username') . '</div>
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
                                    ' . $output_buttons . '
                                    <button type="submit" name="delete_button" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('submitted form')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </div>
    </main>
    ' . output_footer();
    
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
            || (($_POST['form_item_view_page_id']) && ($submitted_form_editable_by_registered_user == '1'))
            || (($_POST['form_item_view_page_id']) && ($user['id'] == $submitter_id) && ($submitted_form_editable_by_submitter == '1'))
        ) {
            $delete_access = TRUE;
        }
        
        // if the user does not have delete access (e.g. form editor), then log activity and output error
        if ($delete_access == FALSE) {
            log_activity("access denied to delete submitted form", $_SESSION['sessionusername']);
            output_error('Access denied. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        
        // get uploaded files for this form, so they can be deleted
        $query = "SELECT
                    files.id,
                    files.name
                 FROM form_data
                 LEFT JOIN files ON form_data.file_id = files.id
                 WHERE (form_data.form_id = '" . escape($_POST['id'] ?? '') . "') AND (form_data.file_id > 0)";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        $files = array();
        
        while($row = mysqli_fetch_assoc($result)) {
            $files[] = $row;
        }
        
        // loop through all files so they can be deleted
        foreach ($files as $file) {
            // if file still exists, delete file
            if ($file['id']) {
                // delete file record
                $query = "DELETE FROM files WHERE id = '" . $file['id'] . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                // delete file
                @unlink(FILE_DIRECTORY_PATH . '/' . $file['name']);
                log_activity(lang(array('string'=>'file ({var:1}) was deleted because the submitted form (form name: {var:2}, reference code: {var:3}) for the file was deleted','vars'=>array($file['name'],$form_name,$reference_code) )), $_SESSION['sessionusername']);
            }
        }
        
        // delete form
        $query = "DELETE FROM forms WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete form data
        $query = "DELETE FROM form_data WHERE (form_id = '" . escape($_POST['id'] ?? '') . "') AND (form_id != '0')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete views for this submitted form that the form view directory feature uses
        pg_sfv_delete_views('submitted_form_id', $_POST['id']);

        log_activity(lang(array('string'=>'submitted form (form name: {var:1}, reference code: {var:2}) was deleted','vars'=>array($form_name,$reference_code) )), $_SESSION['sessionusername']);
        
        // if the there is a form list view send to, then forward user there
        if ((isset($_POST['form_list_view_send_to']) == TRUE) && ($_POST['form_list_view_send_to'] != '')) {
            // Get the form list view page id for this form item view in order to setup liveform correctly.
            $query = "SELECT page_id FROM form_list_view_pages WHERE form_item_view_page_id = '" . escape($_POST['form_item_view_page_id'] ?? '')  . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $form_list_view_page_id = $row['page_id'];

            // If a form list view page was found, then add notice.
            if ($form_list_view_page_id != '') {
                $liveform->remove_form();
                $liveform_form_list_view = new liveform('form_list_view', $form_list_view_page_id);
                
                // add notice that submitted form has been deleted
                $liveform_form_list_view->add_notice(lang('The submitted form has been deleted.'));
            }
            
            header('Location: ' . URL_SCHEME . HOSTNAME . $_POST['form_list_view_send_to']);
            
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
                $query = "SELECT user_id FROM user WHERE user_username = '" . escape($liveform->get_field_value('form_editor_username')) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
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
                 WHERE forms.id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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
                                $error_message = lang(array('string'=>'{var:1} is required.','vars'=>$field['label']));
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
                            $error_message = lang(array('string'=>'{var:1} is required.','vars'=>$field['label']));
                        }
                        
                        $liveform->validate_required_field($field['id'], $error_message);
                    }
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
            // and the visitor entered a value for this field, and pretty URLs are enabled,
            // then check if address name is already in use.
            if (
                ($field['rss_field'] == 'title')
                && ($liveform->check_field_error($field['id']) == false)
                && ($liveform->get_field_value($field['id']) != '')
                && ($pretty_urls == true)
            ) {
                $address_name = create_address_name($liveform->get_field_value($field['id']));

                // If that address name is already in use, then output error.
                if (db_value("SELECT COUNT(*) FROM forms WHERE (page_id = '" . escape($page_id) . "') AND (id != '" . escape($_POST['id'] ?? '') . "') AND (address_name = '" . escape($address_name) . "')") > 0) {
                    $liveform->mark_error($field['id'], lang(array('string'=>'That {var:1} is already in use. Please enter a different one.','vars'=>$field['label'])) );
                }
            }
        }

        // If hooks are enabled and visitor is editing this form from a form item view page,
        // and there is hook code, then run it.
        if (
            (defined('PHP_REGIONS') and PHP_REGIONS === true)
            && ($_POST['form_item_view_page_id'])
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

                                // If there is an existing file, then delete it.
                                if ($file['id'] != '') {
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

                                $user_id = '';

                                // If the user is logged in, then store user id.
                                if (USER_LOGGED_IN == true) {
                                    $user_id = USER_ID;
                                }

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
                                            '$user_id',
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
                                        '" . escape($_POST['id'] ?? '') . "',
                                        '" . $field['id'] . "',
                                        '" . $file_id . "',
                                        '" . escape($field['name']) . "')");

                            // Otherwise if this field is optional and the existing file was set to be deleted, then delete it.
                            } else if (
                                ($field['required'] == 0)
                                && ($liveform->get_field_value($field['id'] . '_delete_file') == 1)
                            ) {
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

                                // If there is an existing file, then delete it.
                                if ($file['id'] != '') {
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
                                     WHERE (form_id = '" . escape($_POST['id'] ?? '') . "') AND (form_id != '0') AND (form_field_id = '" . $field['id'] . "')";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            
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
                                                '" . escape($_POST['id'] ?? '') . "',
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
                                            '" . escape($_POST['id'] ?? '') . "',
                                            '" . $field['id'] . "',
                                            '" . escape(prepare_form_data_for_input($liveform->get_field_value($field['id']), $field['type'])) . "',
                                            '" . escape($field['name']) . "',
                                            '$form_data_type')";
                                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            }
                        }
                    }
                }
            }

            // If pretty URLs are enabled, then update address name.
            if ($pretty_urls == true) {
                $new_address_name = update_submitted_form_address_name($_POST['id']);
            }

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
                
                header('Location: ' . URL_SCHEME . HOSTNAME . $send_to);
                
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
            // if there is a send to, then forward user to send
            if ((isset($_POST['send_to']) == TRUE) && ($_POST['send_to'] != '')) {
                header('Location: ' . URL_SCHEME . HOSTNAME . $_POST['send_to']);
                
            // else forward user to edit submitted form in backend
            } else {
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_submitted_form.php?id=' . $_POST['id']);
            }
        }
    }
}