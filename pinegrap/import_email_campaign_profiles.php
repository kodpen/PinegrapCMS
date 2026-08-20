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
validate_email_access($user);

include_once('liveform.class.php');
$liveform = new liveform('import_email_campaign_profiles');

// If the form has not been submitted, then output it.
if (!$_POST) {

    echo
    pg_page_shell([
        'title'=> lang('Import Campaign Profiles'),
        'extra classes'=>'setting',
        'icon'=>'setting',
        'heading'=>lang('Import Campaign Profiles'),
        'cancel'=>array('enable'=>'true','url'=>'view_email_campaign_profiles.php')
    ,
            'breadcrumb' => array(array('label' => lang('My Campaign Profiles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php'), array('label' => lang('Import Campaign Profiles'))),
        ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Import new and update existing campaign profiles.') . '" title="' . lang('Import Campaign Profiles') . '">[' . lang('Import Campaign Profiles') . ']</h2>
                        <div class="alert alert-danger"><p class="mb-0">' . lang('Please be aware that existing campaign profiles will be updated if the name matches.') . '</p></div>
                    </div>
                </div>

                <form name="form" method="post" enctype="multipart/form-data">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Import CSV') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12  my-2">
                                            <label for="file" class="form-label">' . lang('Select Formatted Text File to Upload') . '</label>
                                            ' . $liveform->output_field(array('type'=>'file', 'id'=>'file', 'name'=>'file', 'size'=>'60', 'class'=>'form-control w-auto')) . '
                                        </div>
                                    </div>
                                </div>       
                            </div> 
                        </div> 
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_import_products" name="submit_button" value="Import Campaign Profiles" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Importing') ) . '" ><span class="material-icons me-2">file_upload</span><span class="btn-text" >' . lang(array('string'=>'Import') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();
    
    $liveform->remove_form('import_email_campaign_profiles');

// Otherwise the form has been submitted so process it.
} else {

    validate_token_field();
    
    // If a file was not selected, then add error and forward user back to previous screen.
    if ($_FILES['file']['name'] == '') {
        $liveform->mark_error('file', lang('Please select a file.'));
        go($_SERVER['PHP_SELF']);
    }

    // Fix Mac line-ending issue.
    ini_set('auto_detect_line_endings', true);
    
    // Get file handle for uploaded CSV file.
    $handle = fopen($_FILES['file']['tmp_name'], 'r');

    // Get column names from first row of CSV file.
    $row = fgetcsv($handle, 100000, ',');
    
    // If the file is empty then add error and forward user back to previous screen.
    if (!$row) {
        $liveform->mark_error('file', lang('The file was empty.'));
        go($_SERVER['PHP_SELF']);
    }

    // Trim all column names.
    $row = array_map('trim', $row);

    $columns = array();
    
    // Loop through the columns in order to determine which are valid.
    foreach ($row as $number => $name) {
        // If this column is valid, then add it to the columns array.
        if (
            ($name == 'name')
            || ($name == 'enabled')
            || ($name == 'action')
            || ($name == 'action_item_id')
            || ($name == 'subject')
            || ($name == 'format')
            || ($name == 'body')
            || ($name == 'page_id')
            || ($name == 'from_name')
            || ($name == 'from_email_address')
            || ($name == 'reply_email_address')
            || ($name == 'bcc_email_address')
            || ($name == 'schedule_time')
            || ($name == 'schedule_length')
            || ($name == 'schedule_unit')
            || ($name == 'schedule_period')
            || ($name == 'schedule_base')
            || ($name == 'purpose')
        ) {
            $columns[$name] = array(
                'name' => $name,
                'number' => $number);
        }
    }

    // If no valid columns were found then add error and forward user back to previous screen.
    if (!$columns) {
        $liveform->mark_error('file', lang('Sorry, we could not find any valid column names in the CSV file.'));
        go($_SERVER['PHP_SELF']);
    }

    // If there is no name column then add error and forward user back to previous screen.
    if (!$columns['name']) {
        $liveform->mark_error('file', lang('Sorry, we could not find a \'name\' column in the CSV file.'));
        go($_SERVER['PHP_SELF']);
    }
    
    // Prepare to keep track of how many campaign profiles were imported and updated.
    $imported_count = 0;
    $updated_count = 0;

    // Loops through all rows of data in CSV file, in order to create or update campaign profiles.
    while ($row = fgetcsv($handle, 100000, ',')) {
        // Trim all values.
        $row = array_map('trim', $row);

        $name = $row[$columns['name']['number']];

        // If the name is blank, then skip this row.
        if ($name == '') {
            continue;
        }

        // If an existing campaign profile has this name, then update campaign profile.
        if ($id = db_value("SELECT id FROM email_campaign_profiles WHERE name = '" . e($name) . "'")) {
            $sql_columns = '';

            // Loop through columns to build SQL update values.
            foreach ($columns as $column) {
                // If this is the schedule time column then prepare time data for db.
                if ($column['name'] == 'schedule_time') {
                    $value = prepare_form_data_for_input($row[$column['number']], 'time');

                } else {
                    $value = $row[$column['number']];
                }

                $sql_columns .= $column['name'] . " = '" . e($value) . "',";
            }

            db(
                "UPDATE email_campaign_profiles 
                SET
                    $sql_columns
                    last_modified_user_id = '" . USER_ID . "',
                    last_modified_timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . e($id) . "'");
            
            $updated_count++;

        // Otherwise an existing campaign profile was not found, so create a new one.
        } else {
            $sql_columns = '';
            $sql_values = '';

            // Loop through columns to build SQL update values.
            foreach ($columns as $column) {
                $sql_columns .= $column['name'] . ",";

                // If this is the schedule time column then prepare time data for db.
                if ($column['name'] == 'schedule_time') {
                    $value = prepare_form_data_for_input($row[$column['number']], 'time');
                    
                } else {
                    $value = $row[$column['number']];
                }

                $sql_values .= "'" . e($value) . "',";
            }

            // If an enabled column was not included,
            // then set campaign profile to be enabled by default.
            if (!$columns['enabled']) {
                $sql_columns .= "enabled,";
                $sql_values .= "'1',";
            }

            db(
                "INSERT INTO email_campaign_profiles (
                    $sql_columns
                    created_user_id,
                    created_timestamp,
                    last_modified_user_id,
                    last_modified_timestamp)
                VALUES (
                    $sql_values
                    '" . USER_ID . "',
                    UNIX_TIMESTAMP(),
                    '" . USER_ID . "',
                    UNIX_TIMESTAMP())");

            $imported_count++;
        }
    }

    $liveform_view_email_campaign_profiles = new liveform('view_email_campaign_profiles');
    
    if (($imported_count > 0) && ($updated_count > 0)) {
        $message =  lang(array('string'=>'{var:1} {var:3} have been imported, and {var:2} {var:3} have been updated.','vars'=>array( number_format($imported_count), number_format($updated_count),lang('campaign profile(s)'))));
        log_activity($message, $_SESSION['sessionusername']);

    } else if ($imported_count > 0) {
        $message = lang(array('string'=>'{var:1} {var:2} have been imported.','vars'=>array(number_format($imported_count),lang('campaign profile(s)'))));
        log_activity($message, $_SESSION['sessionusername']);

    } else if ($updated_count > 0) {
        $message = lang(array('string'=>'{var:1} {var:2} have been updated.','vars'=>array(number_format($updated_count),lang('campaign profile(s)'))));
        log_activity($message, $_SESSION['sessionusername']);

    } else {
        $message = lang(array('string'=>'No {var:1} have been imported or updated.','vars'=>array(lang('campaign profile'))));
    }

    $liveform_view_email_campaign_profiles->add_notice($message);

    go(PATH . SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php');
}