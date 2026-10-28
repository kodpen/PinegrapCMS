<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The submitted form's fields, shared by the create and the edit screens
 * (add_submitted_form.php, edit_submitted_form.php): the "Form Data" field
 * markup of a custom form, the type checks of a posted value, the stored
 * upload and the form_data rows of a posted value. The screens keep what
 * differs: who may open them, the required checks (the edit screen also
 * takes posts from a site's form item view page), the forms row itself,
 * replacing or deleting an existing file, notifications and redirects.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The "Form Data" fields of a custom form, with the scripts its fields need.
 *
 * A new submitted form ($form_id '') offers the signature pad and a plain
 * upload; an existing one shows its signature (never redrawn), its stored
 * file with replace and delete, and a hidden field before each check box
 * group so an unchecked group still arrives in the post.
 *
 * @param liveform $liveform
 * @param array $user
 * @param int|string $page_id  The custom form's page
 * @param bool $office_use_only_access  Office use only fields are shown too
 * @param int|string $form_id  The submitted form, '' while it is created
 * @return array  'fields' (markup), 'wysiwyg_javascript', 'enctype'
 */
function pg_submitted_form_fields_markup($liveform, $user, $page_id, $office_use_only_access, $form_id = '')
{
    $existing = ($form_id !== '');

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
             WHERE page_id = ?
             ORDER BY sort_order";
    $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');

    $fields = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $fields[] = $row;
    }

    $wysiwyg_fields = array();

    $output_fields = '';
    $picker_kinds = array();
    $signature_exists = false;

    // Prepare to keep track if whether there are file upload fields or not,
    // in order to know later if we need to output enctype for form.
    $file_upload_exists = false;

    foreach ($fields as $field) {
        $field_required = '';

        // If this is an office use only field and the user may not edit those from this screen, then skip it.
        if (($field['office_use_only'] != 0) && ($office_use_only_access == false)) {
            continue;
        }

        // if field is for office use only, then prepare to apply office use only style to row
        if ($field['office_use_only'] == 1) {
            $row_class = 'fg-secondary';

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
                     WHERE form_field_id = ?
                     ORDER BY sort_order";
            $result = db_q($query, array((string) $field['id'])) or output_error('Query failed.');

            $options = array();

            while ($row = mysqli_fetch_assoc($result)) {
                $options[] = $row;
            }
        }

        // if field should have spacing above, add spacing
        if ($field['spacing_above']) {
            $output_fields .=
                '<div class="py-3 ' . $row_class . '"></div>';
        }

        switch ($field['type']) {
            case 'text box':
            case 'email address':
                $output_fields .=
                    '<div class="col-12 md:col-6 lg:col-4 my-3 ' . $row_class . '">
                        ' . pg_ai_write_label_row('<label class="form-label" for="' . h($field['id']) . '">' . h($field['label']) . '</label>', pg_ai_write_form_button($user, $page_id, $field['id'])) . '
                        ' . $liveform->output_field(array('type'=>'text', 'name'=>$field['id'], 'id'=>$field['id'], 'value'=>$field['default_value'], 'size'=>$field['size'], 'class'=>'form-control', 'maxlength'=>$field['maxlength'])) . '
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
                    '<div class="col-12 my-3 ' . $row_class . '">
                        ' . pg_ai_write_label_row('<label class="form-label" for="' . h($field['id']) . '">' . h($field['label']) . '</label>', pg_ai_write_form_button($user, $page_id, $field['id'])) . '
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
                    '<div class="col-12 md:col-6 lg:col-4 my-3 ' . $row_class . '">
                        <label class="form-label">' . h($field['label']) . '</label>
                        ' . $liveform->output_field(array('type'=>'select', 'name'=>$name, 'value'=>$field['default_value'], 'options'=>$pick_list_options, 'class'=>'form-control', 'size'=>$field['size'], 'multiple'=>$multiple)) . '
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
                    <div class="form-field">
                        ' . $liveform->output_field(array('type'=>'radio', 'name'=>$field['id'], 'id'=>'software_option_' . $option['id'], 'value'=>$option['value'], 'checked'=>$checked, 'class'=>'radio')) . '
                        <label for="software_option_' . $option['id'] . '"> ' . h($option['label']) . '</label>
                    </div>';
                }

                $output_fields .=
                    '<div class="col-12 my-3 ' . $row_class . '">
                        <label class="form-label">' . h($field['label']) . '</label>
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
                    <div class="form-field">
                        ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>$name, 'id'=>'software_option_' . $option['id'], 'value'=>$option['value'], 'checked'=>$checked, 'class'=>'check')) . '
                        <label for="software_option_' . $option['id'] . '"> ' . h($option['label']) . '</label>
                    </div>';
                }

                // On an existing submitted form the hidden field puts the group in the
                // post even when nothing is checked, because the save replaces only the
                // fields found in the post.  It has to stay BEFORE the check boxes: when
                // the group's name is an array, PHP replaces this empty scalar value with
                // the array of checked values, while a hidden field after them would
                // replace that array with an empty string and lose every checked value.
                // A new submitted form has no data to replace, so it needs none.
                $output_hidden = '';

                if ($existing) {
                    $output_hidden = '<input type="hidden" name="' . $field['id'] . '" />';
                }

                $output_fields .=
                    '<div class="col-12 my-3 ' . $row_class . '">
                        <label class="form-label">' . h($field['label']) . '</label>
                        ' . $output_hidden . '
                        ' . $output_options . '
                    </div>';

                break;

            case 'signature':
                // A signature on an existing submitted form is shown, never offered. Once
                // drawn it is evidence, and a control that could redraw or clear it would
                // make the record worth less than the paper it replaces. The row is
                // checked against its seal because this screen is where someone would
                // look after suspecting the database was edited.
                if ($existing) {
                    $signature_record = function_exists('pg_signature_record')
                        ? pg_signature_record($form_id, $field['id'])
                        : false;

                    $output_fields .=
                    '<div class="col-12 border py-3 my-3 ' . $row_class . '">
                        <label class="form-label">' . h($field['label']) . '</label>
                        ' . ($signature_record
                            ? pg_signature_display($signature_record)
                            : '<div class="fg-secondary">' . lang('Not signed') . '</div>') . '
                    </div>';

                // A new one gets the same pad the visitor signs on. An operator entering a
                // form that arrived on paper, or sitting with the customer, signs here;
                // nothing about the record differs, and who was logged in is part of it.
                } else {
                    $output_fields .=
                    '<div class="col-12 border py-3 my-3 ' . $row_class . '">
                        ' . pg_signature_field($field) . '
                        ' . $field_required . '
                    </div>';

                    $signature_exists = true;
                }

                break;

            case 'file upload':
                $output_file_info = '';
                $output_upload_label = '';
                $output_delete_option = '';

                if ($existing) {
                    // Get file name and size for file if a file exists.
                    $file = db_item(
                        "SELECT
                            files.name,
                            files.size
                        FROM form_data
                        LEFT JOIN files on form_data.file_id = files.id
                        WHERE
                            (form_data.form_id = '" . escape($form_id) . "')
                            AND (form_data.form_field_id = '" . $field['id'] . "')");

                    // If there is an existing file for this field, then output info for that.
                    if ($file['name'] != '') {
                        $output_file_info = '
                        <a class=" btn btn-subtle theme-secondary border" href="' . OUTPUT_PATH . h(encode_url_path($file['name'])) . '" target="_blank">
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
                                    <div class="form-field">
                                        <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=> $field['id'] . '_delete_file', 'id'=> $field['id'] . '_delete_file', 'value'=>'1', 'class'=>'danger no-bs')) . '</div>
                                        <label class="fg-danger" for="' . $field['id'] . '_delete_file">' . lang('Delete File') . '</label>
                                    </div>
                                </div>
                            </div>';
                        }
                    }
                }

                $output_fields .=
                '<div class="col-12 border py-3 my-3 ' . $row_class . '">
                    <label class="form-label">' . h($field['label']) . '</label>
                    <div class="input-group d-flex flex-row flex-wrap">
                        ' . $output_file_info . '
                        ' . $output_upload_label . $liveform->output_field(array('type'=>'file', 'name'=>$field['id'], 'class'=>'form-control', 'style'=>'min-width:200px;', 'size'=>$field['size'] )) . $output_delete_option . '
                    </div>
                    ' . ($existing ? '' : $field_required) . '
                </div>';

                $file_upload_exists = true;

                break;

            case 'date':
                $picker_kinds['date'] = true;
                $output_fields .=
                    '<div class="col-12 md:col-6 lg:col-4 my-3 ' . $row_class . '">
                        <label class="form-label">' . h($field['label']) . '</label>
                        ' . $liveform->output_field(array('type'=>'text', 'id' => $field['id'], 'name'=>$field['id'], 'value'=>$field['default_value'], 'size'=>$field['size'], 'class'=>'form-control', 'maxlength'=>'10', 'data-pg-date' => '')) . '
                        ' . $field_required . '
                    </div>';
                break;

            case 'date and time':
                $picker_kinds['date and time'] = true;
                $output_fields .=
                    '<div class="col-12 md:col-6 lg:col-4 my-3 ' . $row_class . '">
                        <label class="form-label">' . h($field['label']) . '</label>
                        ' . $liveform->output_field(array('type'=>'text', 'id' => $field['id'], 'name'=>$field['id'], 'value'=>$field['default_value'], 'size'=>$field['size'], 'class'=>'form-control', 'maxlength'=>'22', 'data-pg-datetime' => '')) . '
                        ' . $field_required . '
                    </div>';
                break;

            case 'information':
                $output_fields .=
                    '<div class="py-5 ' . $row_class . '">' . $field['information'] . '</div>';
                break;

            case 'time':
                $picker_kinds['time'] = true;
                $output_fields .=
                    '<div class="col-12 md:col-6 lg:col-4 my-3 ' . $row_class . '">
                        <label class="form-label">' . h($field['label']) . '</label>
                        ' . $liveform->output_field(array('type'=>'text', 'id' => $field['id'], 'name'=>$field['id'], 'value'=>$field['default_value'], 'size'=>$field['size'], 'class'=>'form-control', 'maxlength'=>'11', 'data-pg-time' => '')) . '
                        ' . $field_required . '
                        <div class="form-text text-end">' . lang('Format: h:mm AM/PM') . '</div>
                    </div>';
                break;

            default:
                $output_fields .=
                    '<div class="col-12 my-3 ' . $row_class . '">
                        <label class="form-label">' . h($field['label']) . '</label>
                    </div>';

                break;
        }

        // if field should have spacing below, add spacing
        if ($field['spacing_below']) {
            $output_fields .=
                '<div class="py-3 ' . $row_class . '"></div>';
        }
    }

    // The picker options once, after the fields: they are drawn when the
    // page has loaded and all read the one datetimepicker_options, so a form
    // with a date and time field gets the variant with the time wording.
    if (isset($picker_kinds['date and time'])) {
        $output_fields .= get_date_time_picker_format();
    } elseif (isset($picker_kinds['date'])) {
        $output_fields .= get_date_picker_format();
    }

    if (isset($picker_kinds['time'])) {
        $output_fields .= get_time_picker_format();
    }

    if ($picker_kinds) {
        $output_fields .= pg_fields_script();
    }

    $output_wysiwyg_javascript = '';

    // if there is at least one wysiwyg field, prepare wysiwyg fields
    if ($wysiwyg_fields) {
        $output_wysiwyg_javascript = get_wysiwyg_editor_code($wysiwyg_fields);
    }

    // The capture script, once, when this form has a signature pad.
    if ($signature_exists && function_exists('pg_signature_includes')) {
        $output_fields .= pg_signature_includes();
    }

    return array(
        'fields' => $output_fields,
        'wysiwyg_javascript' => $output_wysiwyg_javascript,
        // A file upload field needs a multipart post.
        'enctype' => $file_upload_exists ? ' enctype="multipart/form-data"' : '',
    );
}

/**
 * Marks the type errors of one posted field: a blocked upload name, an
 * invalid date, date and time, e-mail address or time, and a title whose
 * address name another submitted form of the same custom form already uses.
 * The required check stays in the screens.
 *
 * @param liveform $liveform
 * @param array $field  form_fields row: id, label, type, rss_field
 * @param int|string $page_id  The custom form's page
 * @param bool $pretty_urls
 * @param int|string $form_id  The submitted form, '' while it is created
 */
function pg_submitted_form_check_field($liveform, $field, $page_id, $pretty_urls, $form_id = '')
{
    // A file the web server would run or read as its own settings is
    // refused at validation, so the form comes back with the field marked
    // rather than a renamed file quietly landing in the upload folder.
    if (
        ($field['type'] == 'file upload')
        && ($liveform->check_field_error($field['id']) == false)
        && (isset($_FILES[$field['id']]) == true)
        && ($_FILES[$field['id']]['name'] != '')
        && pg_upload_name_blocked($_FILES[$field['id']]['name'])
    ) {
        $liveform->mark_error($field['id'], pg_upload_blocked_message(htmlspecialchars($_FILES[$field['id']]['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
    }

    // if field has date type and there is not already an error for this field and user entered value for field and submitted date is invalid, prepare error
    if (($field['type'] == 'date') && ($liveform->check_field_error($field['id']) == false) && ($liveform->get_field_value($field['id']) != '') && (validate_date($liveform->get_field_value($field['id'])) == false)) {
        $liveform->mark_error($field['id'], lang(array('string'=>'Please enter a valid date for {var:1}','vars'=>h($field['label']))) );
    }

    // if field has date & time type and there is not already an error for this field and user entered value for field and submitted date & time is invalid, prepare error
    if (($field['type'] == 'date and time') && ($liveform->check_field_error($field['id']) == false) && ($liveform->get_field_value($field['id']) != '') && (validate_date_and_time($liveform->get_field_value($field['id'])) == false)) {
        $liveform->mark_error($field['id'], lang(array('string'=>'Please enter a valid date & time for {var:1}','vars'=>h($field['label']))) );
    }

    // if field has email address type and there is not already an error for this field and user entered value for field and submitted e-mail address is invalid, prepare error
    if (($field['type'] == 'email address') && ($liveform->check_field_error($field['id']) == false) && ($liveform->get_field_value($field['id']) != '') && (validate_email_address($liveform->get_field_value($field['id'])) == false)) {
        $liveform->mark_error($field['id'], lang(array('string'=>'Please enter a valid e-mail address for {var:1}','vars'=>h($field['label']))) );
    }

    // if field has time type and there is not already an error for this field and user entered value for field and submitted time is invalid, prepare error
    if (($field['type'] == 'time') && ($liveform->check_field_error($field['id']) == false) && ($liveform->get_field_value($field['id']) != '') && (validate_time($liveform->get_field_value($field['id'])) == false)) {
        $liveform->mark_error($field['id'], lang(array('string'=>'Please enter a valid time for {var:1}','vars'=>h($field['label']))) );
    }

    // If this field is a title field and there is not already an error for this field,
    // and a value was entered for this field, and pretty URLs are enabled,
    // then check if address name is already in use.
    if (
        ($field['rss_field'] == 'title')
        && ($liveform->check_field_error($field['id']) == false)
        && ($liveform->get_field_value($field['id']) != '')
        && ($pretty_urls == true)
    ) {
        $address_name = create_address_name($liveform->get_field_value($field['id']));

        // A new submitted form has no id yet, so every other one counts.
        $sql_other_forms = '';

        if ($form_id !== '') {
            $sql_other_forms = " AND (id != '" . escape($form_id) . "')";
        }

        // If that address name is already in use, then output error.
        if (db_value("SELECT COUNT(*) FROM forms WHERE (page_id = '" . escape($page_id) . "')" . $sql_other_forms . " AND (address_name = '" . escape($address_name) . "')") > 0) {
            $liveform->mark_error($field['id'], lang(array('string'=>'That {var:1} is already in use. Please enter a different one.','vars'=>h($field['label']))) );
        }
    }
}

/**
 * Stores the file posted for a file upload field in the field's upload
 * folder under a free name and creates its files record.
 *
 * @param array $field  form_fields row: id, upload_folder_id
 * @param int|string $user_id  Stored as the file's owner
 * @return int  The new files.id
 */
function pg_submitted_form_store_upload($field, $user_id)
{
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
                ?,
                ?,
                ?,
                ?,
                ?,
                '0',
                '1',
                UNIX_TIMESTAMP())";
    db_q($query, array((string) $file_name, (string) $field['upload_folder_id'], (string) $file_extension, (string) $file_size, (string) $user_id)) or output_error('Query failed.');

    return mysqli_insert_id(db::$con);
}

/**
 * Writes the posted value of one field (not a file upload or signature) as
 * form_data rows: one row per value of a check box group or multiple pick
 * list. Date, date and time and time values keep their type; a rich-text
 * area is stored as html.
 *
 * @param liveform $liveform
 * @param int|string $form_id
 * @param array $field  form_fields row: id, name, type, wysiwyg
 */
function pg_submitted_form_save_value($liveform, $form_id, $field)
{
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

    $values = $liveform->get_field_value($field['id']);

    // A field with one value (not a check box group or pick list) is stored as one row.
    if (is_array($values) == false) {
        $values = array($values);
    }

    foreach ($values as $value) {
        // store form data
        $query = "INSERT INTO form_data (
                    form_id,
                    form_field_id,
                    data,
                    name,
                    type)
                 VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?)";
        db_q($query, array((string) $form_id, (string) $field['id'], (string) prepare_form_data_for_input($value, $field['type']), (string) $field['name'], (string) $form_data_type)) or output_error('Query failed.');
    }
}
