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
        'heading_description' => lang('Import new and update existing campaign profiles.'),
        'cancel'=>array('enable'=>'true','url'=>'view_email_campaign_profiles.php')
    ,
            'breadcrumb' => array(array('label' => lang('My Campaign Profiles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php'), array('label' => lang('Import Campaign Profiles'))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">

                        <div class="alert theme-danger"><p class="mb-0">' . lang('Please be aware that existing campaign profiles will be updated if the name matches.') . '</p></div>
                    </div>
                </div>

                <form name="form" method="post" enctype="multipart/form-data">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Import CSV') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12  my-3">
                                            <label for="file" class="form-label">' . lang('Select Formatted Text File to Upload') . '</label>
                                            ' . $liveform->output_field(array('type'=>'file', 'id'=>'file', 'name'=>'file', 'size'=>'60', 'class'=>'form-control w-auto', 'required'=>'required')) . '
                                            ' . pg_upload_limit_hint() . '
                                        </div>
                                    </div>
                                </div>       
                            </div> 
                        </div> 
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_import_products" name="submit_button" value="Import Campaign Profiles" class="btn my-1  btn-solid theme-success " ><span class="material-icons me-3">file_upload</span><span class="pg-btn-label" >' . lang(array('string'=>'Import') ) . '</span></button>
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

    $upload_limits = pg_upload_limits();
    $upload_refusal = pg_upload_file_refusal($_FILES['file'], $upload_limits['file_max']);

    if ($upload_refusal !== '') {
        $liveform->mark_error('file', h($upload_refusal));
        go($_SERVER['PHP_SELF']);
    }

    // Get file handle for uploaded CSV file.
    $handle = pg_csv_open($_FILES['file']['tmp_name']);

    // The header line says which separator the file uses (Excel in Turkish
    // writes ';').
    $separator = pg_csv_read_separator($handle);

    // Get column names from first row of CSV file.
    $row = fgetcsv($handle, 100000, $separator, '"', '\\');
    
    // If the file is empty then add error and forward user back to previous screen.
    if (!$row) {
        $liveform->mark_error('file', lang('The file was empty.'));
        go($_SERVER['PHP_SELF']);
    }

    // Trim all column names.
    $row = array_map('trim', $row);

    $columns = array();
    // Headings the import does not know, named in the result.
    $unknown_headings = array();
    
    // Loop through the columns in order to determine which are valid.
    foreach ($row as $number => $name) {
        // The heading is matched whatever its case and spacing ("Subject",
        // "From Name").
        $name = pg_import_heading($name);

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
        } else {
            $unknown_headings[] = $row[$number];
        }
    }

    // If no valid columns were found then add error and forward user back to previous screen.
    if (!$columns) {
        $liveform->mark_error('file', lang('Sorry, we could not find any valid column names in the CSV file.'));
        go($_SERVER['PHP_SELF']);
    }

    // If there is no name column then add error and forward user back to previous screen.
    if (!isset($columns['name'])) {
        $liveform->mark_error('file', lang('Sorry, we could not find a \'name\' column in the CSV file.'));
        go($_SERVER['PHP_SELF']);
    }
    
    // Prepare to keep track of how many campaign profiles were imported and updated.
    $imported_count = 0;
    $updated_count = 0;
    $skipped_count = 0;

    // Empty cells up to the last column used, for rows shorter than that.
    $empty_row = array_fill(0, max(array_column($columns, 'number')) + 1, '');

    // A "campaign sent" trigger is checked as the edit screen checks it:
    // against every profile, with the map kept current as rows are written,
    // so a ring made over several rows of the file is found too.
    require_once(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_profile_form.php');
    $follows = pg_email_campaign_profile_follow_map();
    $profile_ids = array_flip(array_map('intval', db_values("SELECT id FROM email_campaign_profiles")));

    // Rows not written, as array(line, reason).
    $problems = array();
    $next_line = 2;

    // Loops through all rows of data in CSV file, in order to create or update campaign profiles.
    while ($row = fgetcsv($handle, 100000, $separator, '"', '\\')) {
        // The file line the row starts on.
        $line = $next_line;
        $next_line += pg_csv_row_lines($row);

        // Trim all values; a row shorter than the header (a blank line is
        // array(null)) reads its missing cells as empty.
        $row = array_map('trim', array_map('strval', $row)) + $empty_row;

        $name = $row[$columns['name']['number']];

        // If the name is blank, then skip this row.
        if ($name == '') {
            continue;
        }

        $existing_profile = db_item("SELECT id, created_user_id, action, action_item_id FROM email_campaign_profiles WHERE name = '" . e($name) . "'");

        // A basic user only sees and edits the profiles they created
        // (view_email_campaign_profiles.php, edit_email_campaign_profile.php).
        // A row named after another user's profile is neither written over nor
        // created a second time under the same name; it is skipped and counted.
        if (
            $existing_profile
            && (USER_ROLE == 3)
            && (USER_ID != $existing_profile['created_user_id'])
        ) {
            $skipped_count++;
            continue;
        }

        // The trigger the profile has once the row is written: the file's
        // value, else the stored one.
        $action = isset($columns['action']) ? $row[$columns['action']['number']] : ($existing_profile ? $existing_profile['action'] : '');
        $action_item_id = isset($columns['action_item_id']) ? $row[$columns['action_item_id']['number']] : ($existing_profile ? $existing_profile['action_item_id'] : '');
        $follows_profile = (strtolower(trim((string) $action)) == 'email_campaign_sent');

        // A profile that follows itself, one that follows it, or no profile
        // at all is refused: the first two send to the same contact without
        // end, the last never sends.
        if (
            $follows_profile
            && (isset($columns['action']) || isset($columns['action_item_id']))
        ) {
            $reason = pg_email_campaign_profile_follow_problem($existing_profile ? (int) $existing_profile['id'] : 0, $action_item_id, $follows, $profile_ids);

            if ($reason !== '') {
                $problems[] = array($line, $reason);
                continue;
            }
        }

        // If an existing campaign profile has this name, then update campaign profile.
        if ($existing_profile) {
            $id = $existing_profile['id'];
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
            $id = (int) $id;

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
            if (!isset($columns['enabled'])) {
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
            $id = (int) mysqli_insert_id(db::$con);
            $profile_ids[$id] = true;
        }

        // Later rows are checked against what this row wrote.
        if ($follows_profile) {
            $follows[$id] = (int) $action_item_id;
        } else {
            unset($follows[$id]);
        }
    }

    $liveform_view_email_campaign_profiles = new liveform('view_email_campaign_profiles');
    
    if (($imported_count > 0) && ($updated_count > 0)) {
        $message =  lang(array('string'=>'{var:1} {var:3} have been imported, and {var:2} {var:3} have been updated.','vars'=>array( pg_format_number($imported_count, 0), pg_format_number($updated_count, 0),lang('campaign profile(s)'))));
        log_activity($message, $_SESSION['sessionusername']);

    } else if ($imported_count > 0) {
        $message = lang(array('string'=>'{var:1} {var:2} have been imported.','vars'=>array(pg_format_number($imported_count, 0),lang('campaign profile(s)'))));
        log_activity($message, $_SESSION['sessionusername']);

    } else if ($updated_count > 0) {
        $message = lang(array('string'=>'{var:1} {var:2} have been updated.','vars'=>array(pg_format_number($updated_count, 0),lang('campaign profile(s)'))));
        log_activity($message, $_SESSION['sessionusername']);

    } else {
        $message = lang(array('string'=>'No {var:1} have been imported or updated.','vars'=>array(lang('campaign profile'))));
    }

    if ($skipped_count > 0) {
        $skipped_message = lang(array('string'=>'{var:1} {var:2} were skipped because they belong to another user.','vars'=>array(pg_format_number($skipped_count, 0),lang('campaign profile(s)'))));
        log_activity($skipped_message, $_SESSION['sessionusername']);
        $message .= ' ' . $skipped_message;
    }

    $liveform_view_email_campaign_profiles->add_notice($message);

    // The first rows not written, each with its line and reason.
    if ($problems) {
        $liveform_view_email_campaign_profiles->add_warning(implode('<br>', array_map('h', pg_csv_problem_list($problems))));
    }

    if (pg_csv_headings_text($unknown_headings) !== '') {
        $liveform_view_email_campaign_profiles->add_warning(h(lang(array('string' => 'These columns were not recognised and were left out: {var:1}', 'vars' => array(pg_csv_headings_text($unknown_headings))))));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php');
}