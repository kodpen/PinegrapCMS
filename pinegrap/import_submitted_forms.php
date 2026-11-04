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

// A file over post_max_size empties the whole request; say so instead of
// drawing the form again as if nothing had been sent.
pg_upload_refuse_discarded_body();
$user = validate_user();
validate_forms_access($user);

include_once('liveform.class.php');
// pg_field_form_pick_by_name(): which field a CSV heading means.
require_once(PG_FUNCTIONS_DIR . '/includes/forms/field_form.php');
// The custom form picker's labels and the import's column and cell rules.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list.php');
$liveform = new liveform('import_submitted_forms');

if (!$_POST) {
    // Every custom form the user may edit is its own option: same-named forms
    // get their page name, binned ones are marked and go last.
    $custom_form_options = array('-' . lang('Select') . '-' => '')
        + pg_submitted_form_list_options(pg_submitted_form_list_custom_forms());

    // One Summary row of the status column: a label (plain text), its value
    // (HTML) and, unless it is the last row, the line under it.
    $status_row = function ($label, $value, $last) {
        return '
                                        <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                            <dt class="fw-normal">' . h($label) . '</dt>
                                            <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                        </div>';
    };

    print
    pg_page_shell(
        array(
            'title'=> lang('Import Submitted Forms'),
            'extra classes'=>'form',
            'icon'=>'form', 
            'heading'=>lang('Import Submitted Forms'),
            'heading_description' => lang('Upload submitted form data for any existing custom form.'),
            'cancel'=>array('enable'=>'true','url'=>'view_submitted_forms.php'),
        
            'breadcrumb' => array(array('label' => lang('Forms'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_submitted_forms.php'), array('label' => lang('Import Submitted Forms'))),
        )
    ) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <form name="form" action="import_submitted_forms.php" method="post" enctype="multipart/form-data">
                ' . get_token_field() . '
                <!-- Edit screen layout of product_builder.php: the form column, the sticky status column (Summary), the sticky save bar. -->
                <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . lang('Import CSV') . '
                            </h2>
                            <div class="card-body pt-0">
                                <div class="row">
                                    <div class="col-12 md:col-6 my-3">
                                        <label for="file" class="form-label">' . lang('Select Formatted Text File to Upload') . '</label>
                                        ' . $liveform->output_field(array('type'=>'file', 'id'=>'file', 'name'=>'file', 'class'=>'form-control', 'required'=>'required', 'accept'=>'.csv,text/csv,text/plain')) . '
                                        ' . pg_upload_limit_hint() . '
                                    </div>
                                    <div class="col-12 md:col-6 my-3">
                                        <label class="form-label" for="custom_form_page_id">' . lang('Select the Custom Form') . '</label>
                                        ' . $liveform->output_field(array('type'=>'select', 'name'=>'custom_form_page_id', 'id'=>'custom_form_page_id', 'class'=>'form-control', 'required'=>'required', 'options'=>$custom_form_options)) . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . lang('File Format') . '
                            </h2>
                            <div class="card-body pt-0">
                                <p class="my-3">' . lang('The first line names the columns: each heading is the name of a field of the chosen custom form, and every other line is one submitted form. A column whose heading names no field is left out. Separate cells with a comma, semicolon or tab.') . '</p>
                                <p class="mb-3">' . lang('A file written by Export can be imported as it is: its first columns (status, reference code and the other record columns) fill no field, and the status column says whether the form is complete.') . '</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 lg:col-4 2xl:col-3 order-2">
                        <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                        <div class="position-sticky" style="top:3.3rem;">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Summary') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <dl class="mb-0">'
        . $status_row(lang('Custom Forms'), h(number_format(count($custom_form_options) - 1)), false)
        . $status_row(lang('Custom Form'), '<span id="submitted_form_import_summary_form">' . h(lang('None')) . '</span>', false)
        . $status_row(lang('File'), '<span id="submitted_form_import_summary_file">' . h(lang('None')) . '</span>', true) . '
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                        <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="submit_import" name="submit_import" value="Import" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">file_upload</span><span class="pg-btn-label">' . lang('Import Submitted Forms') . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </form>
            <script>
                // The Summary names the chosen custom form and file.
                (function () {
                    var box = document.getElementById("file");
                    var picker = document.getElementById("custom_form_page_id");
                    var file = document.getElementById("submitted_form_import_summary_file");
                    var form = document.getElementById("submitted_form_import_summary_form");
                    var none = file.textContent;
                    function showFile() {
                        file.textContent = (box.files && box.files.length) ? box.files[0].name : none;
                    }
                    function showForm() {
                        var option = picker.options[picker.selectedIndex];
                        form.textContent = (option && option.value !== "") ? option.text : none;
                    }
                    box.addEventListener("change", showFile);
                    picker.addEventListener("change", showForm);
                    showForm();
                })();
            </script>
        </div>
    </div>
</main>' .
    output_footer();
    
    $liveform->unmark_errors();
    $liveform->clear_notices();

} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    $liveform->validate_required_field('custom_form_page_id', lang('Custom Form is required.'));
    
    // if no CSV file was uploaded, prepare error
    if (!$_FILES['file']['name']) {
        $liveform->mark_error('file', lang('Please select a file.'));
    } else {
        $upload_limits = pg_upload_limits();
        $upload_refusal = pg_upload_file_refusal($_FILES['file'], $upload_limits['file_max']);

        if ($upload_refusal !== '') {
            $liveform->mark_error('file', h($upload_refusal));
        }
    }
    
    $custom_form_page_id = $liveform->get_field_value('custom_form_page_id');
    
    // if a custom form was selected, validate user's access to custom form
    if ($custom_form_page_id) {
        // The page must be a custom form (a changed option or a form deleted
        // meanwhile names none); its folder decides the user's access.
        $query = "SELECT page.page_folder FROM page WHERE (page.page_id = ?) AND " . pg_form_page_sql('page');
        $result = db_q($query, array((string) $custom_form_page_id)) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        if (!$row) {
            $liveform->mark_error('custom_form_page_id', lang('The selected custom form could not be found. Please choose a different custom form.'));

        // if user does not have access to custom form, prepare error
        } elseif (check_edit_access($row['page_folder']) == false) {
            log_activity(lang('access denied to import submitted forms because user does not have access to modify folder that custom form is in'), $_SESSION['sessionusername']);
            $liveform->mark_error('custom_form_page_id', lang('You do not have access to import submitted forms for that custom form. Please choose a different custom form.'));
        }
    }
    
    // if an error does not exist
    if ($liveform->check_form_errors() == false) {
        // get file handle for uploaded CSV file
        $handle = pg_csv_open($_FILES['file']['tmp_name']);

        // The header line says which separator the file uses (Excel in
        // Turkish writes ';').
        $separator = pg_csv_read_separator($handle);

        // get column names from first row of CSV file
        $columns = fgetcsv($handle, 100000, $separator, '"', '\\') ?: array();

        $fields = array();
        // Headings that name no field of the form, named in the result.
        $unknown_headings = array();
        // Headings of a field an earlier column already fills, left out.
        $repeated_headings = array();
        $mapped_field_ids = array();

        // A file the export wrote starts with the record's own columns
        // (status, reference code…): they fill no field, even one of the same
        // name, and the status column says whether the record is complete.
        $fixed_columns = pg_submitted_form_import_fixed_columns($columns);
        $status_key = $fixed_columns ? array_search('status', pg_submitted_form_export_columns(), true) : false;

        // loop through all column field names in order to determine which columns have valid field names
        foreach ($columns as $key => $field_name) {
            if ($key < $fixed_columns) {
                continue;
            }

            // get field id from field name if a field exists
            $query =
                "SELECT
                    id,
                    name,
                    type,
                    wysiwyg,
                    multiple
                FROM form_fields
                WHERE
                    (page_id = ?)
                    AND (name = ?)
                ORDER BY id";
            $result = db_q($query, array((string) $custom_form_page_id, (string) $field_name)) or output_error('Query failed.');

            // The name matches by the column's collation (no case, no
            // diacritics): a 'klasör' heading must fill the 'klasör' field,
            // not a 'klasor' field read first.
            $matches = array();
            while ($match = mysqli_fetch_assoc($result)) {
                $matches[] = $match;
            }
            $row = pg_field_form_pick_by_name($matches, $field_name);

            // One column fills a field: a second column of the same field
            // would add its value next to the first one's.
            if ($row && isset($mapped_field_ids[$row['id']])) {
                $repeated_headings[] = $field_name;

            // if a field was found, then add to fields array
            } elseif ($row) {
                $field_id = $row['id'];
                $mapped_field_ids[$field_id] = true;
                
                $fields[] =
                    array(
                        'id' => $field_id,
                        'name' => $row['name'],
                        'type' => $row['type'],
                        'wysiwyg' => $row['wysiwyg'],
                        'multiple' => $row['multiple'],
                        'key' => $key
                    );
            } else {
                $unknown_headings[] = $field_name;
            }
        }
        
        $imported_submitted_forms = 0;
        // File-upload cells that resolve to no file the user may attach and
        // date or time cells that cannot be read, as array(line, reason); a
        // row is named by the file line it starts on.
        $file_problems = array();
        $next_line = 1 + pg_csv_row_lines($columns);
        
        // if at least one valid field was found in CSV file
        if ($fields) {
            $pretty_urls = check_if_pretty_urls_are_enabled($custom_form_page_id);

            // loop through all rows of data in CSV file
            while ($row = fgetcsv($handle, 100000, $separator, '"', '\\')) {
                $line = $next_line;
                $next_line += pg_csv_row_lines($row);

                // A blank line or a row of commas is not a submitted form:
                // pass over it, uncounted.
                if (pg_import_row_is_blank($row)) {
                    continue;
                }

                // insert submitted form
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
                $complete = ($status_key !== false) ? pg_submitted_form_import_complete($row[$status_key] ?? '') : '1';
                $result = db_q($query, array((string) $custom_form_page_id, $complete, (string) $user['id'], (string) generate_form_reference_code(), (string) $user['id'])) or output_error('Query failed.');
                
                $submitted_form_id = mysqli_insert_id(db::$con);
                
                // loop through all fields in order to insert data for field
                foreach ($fields as $field) {
                    $key = $field['key'];
                    
                    $file_id = 0;
                    $data = '';
                    
                    // assume that data should not be added for this field until we find out otherwise
                    $add_data = FALSE;
                    
                    // if this is a file upload field then check if file exists and if user has access to file
                    if ($field['type'] == 'file upload') {
                        // check if file exists with the file name that exists in imported data
                        // it must not be a design file and must not be an attachment already
                        $query =
                            "SELECT
                               id,
                               folder AS folder_id
                            FROM files
                            WHERE
                                (name = ?)
                                AND (design = '0')
                                AND (attachment = '0')";
                        $file_name = isset($row[$key]) ? trim((string) $row[$key]) : '';
                        $result = db_q($query, array($file_name)) or output_error('Query failed.');
                        
                        // if a file exists with the same name then continue to check if user has edit access to file
                        if (mysqli_num_rows($result) > 0) {
                            $file = mysqli_fetch_assoc($result);
                            
                            // if the user has edit access to the folder that the file is in, then remember to add data and mark file as an attachment
                            if (check_edit_access($file['folder_id']) == true) {
                                $add_data = TRUE;
                                $file_id = $file['id'];
                                $data = '';
                                
                                // mark file as an attachment
                                $query = "UPDATE files SET attachment = '1' WHERE id = ?";
                                $result = db_q($query, array((string) $file_id)) or output_error('Query failed.');
                            } else {
                                $file_problems[] = array($line, lang(array('string' => 'The file "{var:1}" is in a folder you cannot edit, so it was not attached to the {var:2} field.', 'vars' => array($file_name, $field['name']))));
                            }

                        // An empty cell attaches nothing and needs no mention.
                        } elseif ($file_name !== '') {
                            $file_problems[] = array($line, lang(array('string' => 'No file named "{var:1}" was found, so nothing was attached to the {var:2} field.', 'vars' => array($file_name, $field['name']))));
                        }

                    // else this is not a file upload field, so add data
                    } else {
                        $add_data = TRUE;
                        // The export guards a leading = + - @ with an apostrophe; take it off.
                        $data = isset($row[$key]) ? pg_csv_formula_unguard($row[$key]) : '';

                        // A date, date and time or time is stored as the add
                        // screen stores it; one that cannot be read is left
                        // empty and its line named.
                        $converted = pg_submitted_form_import_value($data, $field['type']);
                        if ($converted === null) {
                            $file_problems[] = array($line, lang(array('string' => 'The value "{var:1}" is not a date or time this site reads, so the {var:2} field was left empty.', 'vars' => array(trim($data), $field['name']))));
                            $add_data = FALSE;
                        } else {
                            $data = $converted;
                        }
                    }
                    
                    // if data should be added for this field, then add it
                    if ($add_data == TRUE) {
                        // assume that the form data type is standard until we find out otherwise
                        $form_data_type = 'standard';
                        
                        // if the form field's type is date, date and time, or time, then set form data type to the form field type
                        if (
                            ($field['type'] == 'date')
                            || ($field['type'] == 'date and time')
                            || ($field['type'] == 'time')
                        ) {
                            $form_data_type = $field['type'];
                            
                        // else if the form field is a wysiwyg text area, then set type to html
                        } elseif (($field['type'] == 'text area') && ($field['wysiwyg'] == 1)) {
                            $form_data_type = 'html';
                        }

                        // If this field is a pick list and allow multiple selection is enabled,
                        // or if this field is a check box, and a separator exists in the data,
                        // then this data contains multiple values, so add multiple data records.
                        if (
                            (
                                (
                                    ($field['type'] == 'pick list')
                                    && ($field['multiple'] == 1)
                                )
                                || ($field['type'] == 'check box')
                            )
                            && (mb_strpos($data, ',') !== false)
                        ) {
                            $data_values = explode(',', $data);
                            
                            // Loop through all data values for this field in order to add database record for each one.
                            foreach ($data_values as $data_value) {
                                $query =
                                    "INSERT INTO form_data (
                                        form_id,
                                        form_field_id,
                                        file_id,
                                        data,
                                        name,
                                        type)
                                    VALUES (
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        ?)";
                                $result = db_q($query, array((string) $submitted_form_id, (string) $field['id'], (string) $file_id, (string) trim($data_value), (string) $field['name'], (string) $form_data_type)) or output_error('Query failed.');
                            }

                        // Otherwise the data has only one value so add a single record in the database for it.
                        } else {
                            $query =
                                "INSERT INTO form_data (
                                    form_id,
                                    form_field_id,
                                    file_id,
                                    data,
                                    name,
                                    type)
                                VALUES (
                                    ?,
                                    ?,
                                    ?,
                                    ?,
                                    ?,
                                    ?)";
                            $result = db_q($query, array((string) $submitted_form_id, (string) $field['id'], (string) $file_id, (string) $data, (string) $field['name'], (string) $form_data_type)) or output_error('Query failed.');
                        }
                    }
                }

                // If pretty URLs are enabled, then update address name.
                if ($pretty_urls == true) {
                    update_submitted_form_address_name($submitted_form_id);
                }
                
                $imported_submitted_forms++;
            }
            
        // else there was not at least one valid field found, so prepare error
        } else {
            $liveform->mark_error('file', lang('A valid field name could not be found. Please make sure there is at least one valid field name in the first row of the CSV file.'));
            
            fclose($handle);
            
            // send user back to import submitted forms screen
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/import_submitted_forms.php');
            exit();
        }
        
        // if no submitted forms were imported, because there were no rows, prepare error
        if ($imported_submitted_forms == 0) {
            $liveform->mark_error('file', lang('No submitted forms were imported, because there were no rows of data in the CSV file.'));
            
            fclose($handle);
            
            // send user back to import submitted forms screen
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/import_submitted_forms.php');
            exit();
        }
        
        fclose($handle);
        
        $liveform->remove_form('import_submitted_forms');

        log_activity(lang(array('string'=>'{var:1} submitted form(s) were imported','vars'=>$imported_submitted_forms)), $_SESSION['sessionusername']);

        $liveform->add_notice(lang(array('string'=>'{var:1} submitted form(s) have been imported.','vars'=>$imported_submitted_forms)) );

        if (pg_csv_headings_text($unknown_headings) !== '') {
            $liveform->add_warning(h(lang(array('string' => 'These columns name no field of the form and were left out: {var:1}', 'vars' => array(pg_csv_headings_text($unknown_headings))))));
        }

        if (pg_csv_headings_text($repeated_headings) !== '') {
            $liveform->add_warning(h(lang(array('string' => 'These columns name a field an earlier column already fills and were left out: {var:1}', 'vars' => array(pg_csv_headings_text($repeated_headings))))));
        }

        if ($file_problems) {
            $liveform->add_warning(implode('<br>', array_map('h', pg_csv_problem_list($file_problems))));
        }
    }
    
    // send user back to import submitted forms screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/import_submitted_forms.php');
}
?>