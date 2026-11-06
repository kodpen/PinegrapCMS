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
        'extra classes'=>'campaign',
        'icon'=>'campaign',
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
            <form name="form" method="post" enctype="multipart/form-data">
                ' . get_token_field() . '
                <!-- Edit screen layout of product_builder.php: the form column, the sticky status column (Summary), the sticky save bar. -->
                <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . lang('Import CSV') . '
                            </h2>
                            <div class="card-body pt-0">
                                <div class="my-3">
                                    <label for="file" class="form-label">' . lang('Select Formatted Text File to Upload') . '</label>
                                    ' . $liveform->output_field(array('type'=>'file', 'id'=>'file', 'name'=>'file', 'accept'=>'.csv,text/csv', 'class'=>'form-control', 'required'=>'required')) . '
                                    ' . pg_upload_limit_hint() . '
                                </div>
                            </div>
                        </div>
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . lang('File Format') . '
                            </h2>
                            <div class="card-body pt-0">
                                <p class="my-3">' . lang('The first line names the columns: name, enabled, action, action_item_id, subject, format, body, page_id, from_name, from_email_address, reply_email_address, bcc_email_address, schedule_length, schedule_unit, schedule_period, schedule_base, schedule_time, purpose (the headings of the export). Only name is required. Separate cells with a comma, semicolon or tab.') . '</p>
                                <p class="mb-3">' . lang('The export\'s reading columns (action_item, page_name, created, last_modified and their user names) are skipped, so an exported file can be imported again.') . '</p>
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
                                    <dl class="mb-0">
                                        <div class="d-flex justify-content-between gap-3 py-2">
                                            <dt class="fw-normal">' . h(lang('File')) . '</dt>
                                            <dd class="mb-0 fw-bold text-end text-break" id="campaign_profile_import_summary_file">' . h(lang('None')) . '</dd>
                                        </div>
                                    </dl>
                                    <div class="alert theme-danger mt-3 mb-0"><p class="mb-0">' . lang('Please be aware that existing campaign profiles will be updated if the name matches.') . '</p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                        <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="submit_import_email_campaign_profiles" name="submit_button" value="Import Campaign Profiles" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">file_upload</span><span class="pg-btn-label">' . lang('Import') . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </form>
            <script>
                // The Summary names the file once one is chosen.
                (function () {
                    var box = document.getElementById("file");
                    var summary = document.getElementById("campaign_profile_import_summary_file");
                    var none = summary.textContent;
                    box.addEventListener("change", function () {
                        summary.textContent = (box.files && box.files.length) ? box.files[0].name : none;
                    });
                })();
            </script>
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

    // Columns the screen's own export writes for reading only (labels and
    // record history); a re-imported export skips them without a warning.
    $export_only_headings = array('action_item', 'page_name', 'created', 'created_username', 'last_modified', 'last_modified_username');

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
        } elseif (!in_array($name, $export_only_headings, true)) {
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

        $existing_profile = db_item(
            "SELECT
                id, created_user_id, name, enabled, action, action_item_id, subject, format, page_id,
                from_name, from_email_address, reply_email_address, bcc_email_address,
                schedule_time, schedule_length, schedule_unit, schedule_period, schedule_base, purpose
            FROM email_campaign_profiles
            WHERE name = '" . e($name) . "'");

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

        // The profile as it is once the row is written: the file's cells over
        // the stored values, or over the values a new profile starts with.
        $values = $existing_profile ? $existing_profile : array(
            'enabled' => '1',
            'action' => '',
            'action_item_id' => '0',
            'subject' => '',
            'format' => 'plain_text',
            'page_id' => '0',
            'from_name' => '',
            'from_email_address' => '',
            'reply_email_address' => '',
            'bcc_email_address' => '',
            'schedule_time' => '',
            'schedule_length' => '0',
            'schedule_unit' => 'days',
            'schedule_period' => '',
            'schedule_base' => '',
            'purpose' => 'commercial',
        );

        foreach ($columns as $column) {
            $values[$column['name']] = $row[$column['number']];
        }

        $action = (string) $values['action'];
        $action_item_id = (string) $values['action_item_id'];
        $follows_profile = ($action == 'email_campaign_sent');

        // Each row is checked with the rules of the create and edit form, so
        // a value the form refuses is not written either.
        $reason = pg_email_campaign_profile_values_problem($values, ECOMMERCE && USER_MANAGE_ECOMMERCE);

        // The required subject and from name, and every text column within
        // its length: a longer cell would be cut on the write.
        if ($reason === '') {
            $text_problems = pg_email_campaign_profile_text_problems($values);
            $reason = $text_problems ? reset($text_problems) : '';
        }

        // Before the upgrade adds the group action it would be stored as ''.
        if (($reason === '') && in_array($action, pg_email_campaign_profile_group_actions(), true) && !pg_email_campaign_profile_group_action_ready()) {
            $reason = lang('The contact group action waits for the database update that adds it.');
        }

        if ($reason === '') {
            $reason = pg_email_campaign_profile_access_problem($values);
        }

        if ($reason !== '') {
            $problems[] = array($line, $reason);
            continue;
        }

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

        // The columns written: the file's, with the time as stored, and for
        // every action but a calendar event reservation the period and base
        // the form saves ("after action"), whatever the file says.
        $write = array();

        foreach ($columns as $column) {
            $write[$column['name']] = ($column['name'] == 'schedule_time')
                ? pg_email_campaign_profile_schedule_time($row[$column['number']])
                : $row[$column['number']];
        }

        if ($action != 'calendar_event_reserved') {
            $write['schedule_period'] = 'after';
            $write['schedule_base'] = 'action';
        }

        // If an existing campaign profile has this name, then update campaign profile.
        if ($existing_profile) {
            $id = $existing_profile['id'];
            $sql_columns = '';

            foreach ($write as $column_name => $value) {
                $sql_columns .= $column_name . " = '" . e($value) . "',";
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
            // If an enabled column was not included,
            // then set campaign profile to be enabled by default.
            if (!isset($write['enabled'])) {
                $write['enabled'] = '1';
            }

            $sql_columns = '';
            $sql_values = '';

            foreach ($write as $column_name => $value) {
                $sql_columns .= $column_name . ",";
                $sql_values .= "'" . e($value) . "',";
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