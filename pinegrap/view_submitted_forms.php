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

$liveform = new liveform('view_submitted_forms');

// These are only ever appended to further below, so they have to start out empty.
$output = '';

// The filter is carried through the paging links and the advanced filters form.
// One sent as an array (?filter[]=x) counts as not given: it ended in an error
// page.
$filter = (isset($_REQUEST['filter']) && is_string($_REQUEST['filter'])) ? $_REQUEST['filter'] : '';

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string or this is the custom forms array then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (
        (is_string($value) == TRUE)
        || ($key == 'custom_forms')
    ) {
        // if the value is a string, then trim it
        if (is_string($value) == TRUE) {
            $value = trim($value);
        }

        $_SESSION['software']['forms']['view_submitted_forms'][$key] = $value;
    }
}

// if advanced filters value was passed in the query string, turn them on or
// off before the defaults below, so the checked custom forms start out filled
if (isset($_REQUEST['advanced_filters']) == true) {
    $_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] = ($_REQUEST['advanced_filters'] == 'true');
}

// The list's filters, its folder rule and its rows are shared with the
// action that pages it (includes/fn/submitted_form_list.php).
require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list.php');

$custom_forms = pg_submitted_form_list_custom_forms();
pg_submitted_form_list_defaults($custom_forms);
$list_filter = pg_submitted_form_list_filter($user);
$sort_columns = $list_filter['sort_columns'];
$sort_default = $list_filter['sort_default'];
$sort_levels = $list_filter['sort_levels'];
$date_type_column = $list_filter['date_type_column'];

$date_type_last_modified_selected = '';
$date_type_submitted_selected = '';

if (($_SESSION['software']['forms']['view_submitted_forms']['date_type'] ?? '') == 'last modified') {
    $date_type_last_modified_selected = ' selected="selected"';
} else {
    $date_type_submitted_selected = ' selected="selected"';
}

// find the oldest date type timestamp (this will be used later in a couple of places)
$query = "SELECT MIN($date_type_column) FROM forms";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$oldest_timestamp = $row[0];

$decrease_year['start_month'] = '01';
$decrease_year['start_day'] = '01';
$decrease_year['start_year'] = ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? '') - 1;
$decrease_year['stop_month'] = '12';
$decrease_year['stop_day'] = '31';
$decrease_year['stop_year'] = ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? '') - 1;

$current_year['start_month'] = '01';
$current_year['start_day'] = '01';
$current_year['start_year'] = date('Y');
$current_year['stop_month'] = '12';
$current_year['stop_day'] = '31';
$current_year['stop_year'] = date('Y');

$increase_year['start_month'] = '01';
$increase_year['start_day'] = '01';
$increase_year['start_year'] = ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? '') + 1;
$increase_year['stop_month'] = '12';
$increase_year['stop_day'] = '31';
$increase_year['stop_year'] = ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? '') + 1;

// The arrows of the month unit step from the last month on screen
// (pg_list_period_month_steps()).
$month_steps = pg_list_period_month_steps($_SESSION['software']['forms']['view_submitted_forms'] ?? array());
$decrease_month = $month_steps['previous'];

$current_month['new_month'] = date('m');
$current_month['new_year'] = date('Y');
$current_month['start_month'] = $current_month['new_month'];
$current_month['start_day'] = '01';
$current_month['start_year'] = $current_month['new_year'];
$current_month['stop_month'] = $current_month['new_month'];
$current_month['stop_day'] = date('t');
$current_month['stop_year'] = $current_month['new_year'];

$increase_month = $month_steps['next'];

// The week starts on the panel language's first day (Monday in Turkish).
$week_steps = pg_list_period_week_steps($_SESSION['software']['forms']['view_submitted_forms'] ?? array());
$decrease_week = $week_steps['previous'];
$current_week = $week_steps['current'];
$increase_week = $week_steps['next'];

$decrease_day['new_time'] = mktime(0, 0, 0, ($_SESSION['software']['forms']['view_submitted_forms']['start_month'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['start_day'] ?? '') - 1, ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? ''));
$decrease_day['new_month'] = date('m', $decrease_day['new_time']);
$decrease_day['new_day'] = date('d', $decrease_day['new_time']);
$decrease_day['new_year'] = date('Y', $decrease_day['new_time']);
$decrease_day['start_month'] = $decrease_day['new_month'];
$decrease_day['start_day'] = $decrease_day['new_day'];
$decrease_day['start_year'] = $decrease_day['new_year'];
$decrease_day['stop_month'] = $decrease_day['new_month'];
$decrease_day['stop_day'] = $decrease_day['new_day'];
$decrease_day['stop_year'] = $decrease_day['new_year'];

$current_day['new_month'] = date('m');
$current_day['new_day'] = date('d');
$current_day['new_year'] = date('Y');
$current_day['start_month'] = $current_day['new_month'];
$current_day['start_day'] = $current_day['new_day'];
$current_day['start_year'] = $current_day['new_year'];
$current_day['stop_month'] = $current_day['new_month'];
$current_day['stop_day'] = $current_day['new_day'];
$current_day['stop_year'] = $current_day['new_year'];

$increase_day['new_time'] = mktime(0, 0, 0, ($_SESSION['software']['forms']['view_submitted_forms']['start_month'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['start_day'] ?? '') + 1, ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? ''));
$increase_day['new_month'] = date('m', $increase_day['new_time']);
$increase_day['new_day'] = date('d', $increase_day['new_time']);
$increase_day['new_year'] = date('Y', $increase_day['new_time']);
$increase_day['start_month'] = $increase_day['new_month'];
$increase_day['start_day'] = $increase_day['new_day'];
$increase_day['start_year'] = $increase_day['new_year'];
$increase_day['stop_month'] = $increase_day['new_month'];
$increase_day['stop_day'] = $increase_day['new_day'];
$increase_day['stop_year'] = $increase_day['new_year'];

// The range on screen, in the site's date order.
$output_date_range_time = h(pg_list_period_heading($_SESSION['software']['forms']['view_submitted_forms'] ?? array()));

// if user requested to export forms, export forms
if (($_GET['submit_data'] ?? '') == 'Export Forms') {
    if (($_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] ?? '') == true) {
        $custom_form_page_id = $_SESSION['software']['forms']['view_submitted_forms']['custom_forms'][0];
    } else {
        $custom_form_page_id = ($_SESSION['software']['forms']['view_submitted_forms']['custom_form'] ?? '');
    }

    pg_csv_download_start('forms.csv');

    echo '"form_name","reference_code","status","address_name","tracking_code","affiliate_code","http_referer","ip_address","submitted","last_modified","member_id","form_editor"';

    // check if custom form is a quiz
    $query =
        "SELECT quiz
        FROM custom_form_pages
        WHERE page_id = ?";
    $result = db_q($query, array((string) $custom_form_page_id)) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $quiz = $row['quiz'];

    // if custom form is a quiz, then include quiz score column
    if ($quiz == 1) {
        print ',"quiz_score"';
    }

    // get fields for custom form
    $query = "SELECT name
             FROM form_fields
             WHERE (page_id = ?) AND (type != 'information') AND (type != 'file upload')
             ORDER BY sort_order";
    $result = db_q($query, array((string) $custom_form_page_id)) or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        // replace quotation mark with two quotation marks
        $column_name = str_replace('"', '""', $row['name']);

        print ',"' . $column_name . '"';
    }

    print "\n";

    // get all forms that match filters (the folder rule is part of them)
    $query =
        "SELECT
            forms.id,
            custom_form_pages.form_name,
            forms.quiz_score,
            forms.reference_code,
            forms.complete,
            forms.address_name,
            forms.tracking_code,
            forms.affiliate_code,
            forms.http_referer,
            INET_NTOA(forms.ip_address) AS ip_address,
            forms.submitted_timestamp,
            forms.last_modified_timestamp,
            contacts.member_id,
            form_editor_user.user_username as form_editor
        " . $list_filter['from'] . "
        " . $list_filter['where'] . "
        ORDER BY " . $list_filter['order_by'];
    $result = db_q($query, $list_filter['params']) or output_error('Query failed.');

    $forms = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $forms[] = $row;
    }

    $number_of_forms = 0;

    foreach ($forms as $form) {
        $form_name = csv_cell($form['form_name']);
        $quiz_score = $form['quiz_score'];
        $reference_code = csv_cell($form['reference_code']);

        if ($form['complete']) {
            $status = lang('Complete');
        } else {
            $status = lang('Incomplete');
        }

        $address_name = csv_cell($form['address_name']);
        $tracking_code = csv_cell($form['tracking_code']);
        $affiliate_code = csv_cell($form['affiliate_code']);
        $http_referer = csv_cell($form['http_referer']);
        $submitted = pg_csv_export_time($form['submitted_timestamp']);
        $last_modified = pg_csv_export_time($form['last_modified_timestamp']);
        $member_id = csv_cell($form['member_id']);
        $form_editor = csv_cell($form['form_editor']);

        $ip_address = $form['ip_address'];
        
        // If the IP address is 0.0.0.0, then we don't know the IP address, so set the value to empty string.
        if ($ip_address == '0.0.0.0') {
            $ip_address = '';
        }

        print '"' . $form_name . '","' . $reference_code . '","' . $status . '","' . $address_name . '","' . $tracking_code . '","' . $affiliate_code . '","' . $http_referer . '","' . $ip_address . '","' . $submitted . '","' . $last_modified . '","' . $member_id . '","' . $form_editor . '"';

        // if custom form is a quiz, then include quiz score
        if ($quiz == 1) {
            print ',"' . $quiz_score . '"';
        }

        // get data for all fields for this form
        $query = "SELECT
                    form_fields.id,
                    form_data.data
                 FROM form_fields
                 LEFT JOIN form_data ON ((form_fields.id = form_data.form_field_id) AND (form_data.form_id = ?))
                 WHERE (form_fields.page_id = ?) AND (form_fields.type != 'information') AND (form_fields.type != 'file upload')
                 ORDER BY form_fields.sort_order";
        $result = db_q($query, array((string) $form['id'], (string) $custom_form_page_id)) or output_error('Query failed.');

        $fields = array();

        while ($row = mysqli_fetch_assoc($result)) {
            // if this field id has already been used then that means that this field has multiple values, so add values separated by commas
            if (isset($fields[$row['id']]) == true) {
                $fields[$row['id']] .= ', ' . $row['data'];

            // else this field id has not been used, so just add data normally
            } else {
                $fields[$row['id']] = $row['data'];
            }
        }

        // loop through all fields so we can output data to csv file
        foreach ($fields as $data) {
            $data = csv_cell($data);
            print ',"' . $data . '"';
        }

        print "\n";

        $number_of_forms++;
    }

    log_activity(lang(array('string'=>'{var:1} submitted form(s) were exported','vars'=>$number_of_forms)), $_SESSION['sessionusername']);

// if mass deletion is allowed and user requested to delete forms, delete forms
} elseif ((MASS_DELETION == true) && (($_GET['submit_data'] ?? '') == 'Delete Forms')) {
    // Mass deletion is triggered by a GET form, so require the token to block forged links.
    validate_token_field();

    // get all forms that match filters (the folder rule is part of them)
    $query = "SELECT
                forms.id,
                forms.page_id,
                custom_form_pages.form_name
             " . $list_filter['from'] . "
             " . $list_filter['where'];
    $result = db_q($query, $list_filter['params']) or output_error('Query failed.');

    $forms = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $forms[] = $row;
    }

    $custom_forms_for_log = array();

    $number_of_forms = 0;

    foreach ($forms as $form) {
        // get uploaded files for this form, so they can be deleted
        $query = "SELECT
                    files.id,
                    files.name
                 FROM form_data
                 LEFT JOIN files ON form_data.file_id = files.id
                 WHERE (form_data.form_id = ?) AND (form_data.file_id > 0)";
        $result = db_q($query, array((string) $form['id'])) or output_error('Query failed.');

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
            }
        }

        // delete form
        $query = "DELETE FROM forms WHERE id = ?";
        $result = db_q($query, array((string) $form['id'])) or output_error('Query failed.');

        // delete form data
        $query = "DELETE FROM form_data WHERE (form_id = ?) AND (form_id != '0')";
        $result = db_q($query, array((string) $form['id'])) or output_error('Query failed.');

        // delete signature records for this submitted form
        pg_signature_delete($form['id']);
        
        // delete views for this submitted form that the form view directory feature uses
        pg_sfv_delete_views('submitted_form_id', $form['id']);

        $custom_forms_for_log[$form['page_id']] = $form['form_name'];

        $number_of_forms++;
    }

    // prepare list of custom forms for log
    $custom_form_list = '';

    $count = 1;

    foreach ($custom_forms_for_log as $custom_form_name) {
        $custom_form_list .= $custom_form_name;

        // if custom form is not the last custom form, then add a comma for log
        if ($count < count($custom_forms_for_log)) {
            $custom_form_list .= ', ';
        }

        $count++;
    }

    // if at least one form was deleted
    if ($number_of_forms > 0) {
        log_activity(lang(array('string'=>'{var:1} submitted form(s) from custom form(s) ({var:2}) were deleted','vars'=>array(pg_format_number($number_of_forms, 0),$custom_form_list ) )) , $_SESSION['sessionusername']);

        $liveform->add_notice( lang(array('string'=>'{var:1} form(s) were deleted','vars'=>pg_format_number($number_of_forms, 0))) );
    } else {
        $liveform->add_notice(lang('No forms were deleted'));
    }

    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_submitted_forms.php');

// else view forms
} else {
    // get minimum year from oldest timestamp
    $oldest_year = date('Y', $oldest_timestamp);
    if (($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? '') < $oldest_year) {
        $oldest_year = ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? '');
    }

    $this_year = date('Y');
    if (($_SESSION['software']['forms']['view_submitted_forms']['stop_year'] ?? '') > $this_year) {
        $this_year = ($_SESSION['software']['forms']['view_submitted_forms']['stop_year'] ?? '');
    }

    $years = array();

    // create html for year options
    for ($i = $oldest_year; $i <= $this_year; $i++) {
        $years[] = $i;
    }

    $total_number_of_forms = 0;

    $output_custom_form_options = '';

    // create custom form selection list
    foreach ($custom_forms as $custom_form) {
        // if the custom form is equal to selected custom form
        if ($custom_form['id'] == ($_SESSION['software']['forms']['view_submitted_forms']['custom_form'] ?? '')) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }

        // get number of forms for custom form
        $query = "SELECT COUNT(id)
                 FROM forms
                 WHERE page_id = ?";
        $result = db_q($query, array((string) $custom_form['id'])) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $number_of_forms = $row[0];

        // if the advanced filters are not on, then prepare custom form picklist
        if (($_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] ?? '') == false) {
            $output_custom_form_options .= '<option value="' . $custom_form['id'] . '"' . $selected . '>' . h($custom_form['name']) . ' (' . $number_of_forms . ')</option>';
        }

        $total_number_of_forms += $number_of_forms;
    }

    // if the advanced filters are not on, then prepare custom form picklist
    if (($_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] ?? '') == false) {

        // if all custom form is selected
        if (($_SESSION['software']['forms']['view_submitted_forms']['custom_form'] ?? '') == '[All]') {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }

        $output_custom_form_options = '<option value="[All]"' . $selected . '>[' . lang('All') . '] (' . $total_number_of_forms . ')</option>' . $output_custom_form_options;
    }

    // The rows come one page at a time from the submitted_form_list_rows
    // action (includes/panel/lists.php), which reads the same filters from
    // the session.

    // if the advanced filters are off
    if (($_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] ?? '') == false) {
        
        $output_custom_form_selection = '
            <label class="input-group-text material-icons" title="' . lang('Custom Form') . '" for="custom_form">format_list_bulleted</label>
            <select id="custom_form" aria-label="' . h(lang('Custom Form')) . '" name="custom_form" class="form-control" title="' . lang('Custom Form') . '" onchange="submit_form(\'advanced_filters_form\')">' . $output_custom_form_options . '</select>';
        $output_advanced_filters_value = 'true';
        $output_advanced_filters_label = lang('Add Advanced Filters');
        $output_advanced_filters = '';
        $advanced_filters_icon = 'bi-funnel';
        $output_advanced_filters_class = '';

      
    // else the advanced filters are on
    } else {
       
        $output_custom_form_selection = '
        <label class="input-group-text material-icons" title="' . lang('Custom Form') . '" for="custom_form_fake">format_list_bulleted</label>
        <select id="custom_form_fake" aria-label="' . h(lang('Custom Form')) . '" name="custom_form_fake" disabled class="form-control disabled" title="' . lang('Custom Form') . '"><option value="">' . lang('Disabled') . '</option></select>';
        $output_advanced_filters_value = 'false';
        $output_advanced_filters_label = lang('Remove Advanced Filters');
        $advanced_filters_icon = 'bi-funnel-fill';
        $output_advanced_filters_class = ' active';
        



        // Prepare selection for status field.
        $status_any_selected = '';
        $status_incomplete_selected = '';
        $status_complete_selected = '';

        if (($_SESSION['software']['forms']['view_submitted_forms']['status'] ?? '') == 'any') {
            $status_any_selected = ' selected="selected"';

        } elseif (($_SESSION['software']['forms']['view_submitted_forms']['status'] ?? '') == 'incomplete') {
            $status_incomplete_selected = ' selected="selected"';

        } elseif (($_SESSION['software']['forms']['view_submitted_forms']['status'] ?? '') == 'complete') {
            $status_complete_selected = ' selected="selected"';
        }

        // Both are appended to inside the loop below, under conditions that do
        // not hold for every custom form, so they have to start out empty.
        $output_form_fields = '';
        $output_custom_forms = '';

        foreach ($custom_forms as $custom_form) {
            // get number of forms for custom form
            $query = "SELECT COUNT(id)
                     FROM forms
                     WHERE page_id = ?";
            $result = db_q($query, array((string) $custom_form['id'])) or output_error('Query failed.');
            $row = mysqli_fetch_row($result);
            $number_of_forms = $row[0];

            // if this custom form should be checked
            if ((is_array(($_SESSION['software']['forms']['view_submitted_forms']['custom_forms'] ?? array())) == true) && (in_array($custom_form['id'], ($_SESSION['software']['forms']['view_submitted_forms']['custom_forms'] ?? array())) == true)) {
                $checked = ' checked="checked"';

                // get fields for custom form, so user can enter a filter for fields
                $query = "SELECT
                            form_fields.id,
                            form_fields.name
                         FROM form_fields
                         WHERE (form_fields.page_id = ?) AND (form_fields.type != 'information') AND (form_fields.type != 'file upload')
                         ORDER BY form_fields.sort_order";
                $result = db_q($query, array((string) $custom_form['id'])) or output_error('Query failed.');

                while ($row = mysqli_fetch_assoc($result)) {
                    $field_id = $row['id'];
                    $field_name = $row['name'];

                    $output_form_fields .=
                    '<div class="col-12 sm:col-6 md:col-12 my-1">
                        <label for="form_field_' . $field_id . '" class="form-label">' . h($custom_form['name']) . ' :: ' . h($field_name) . '</label>
                        <input type="text" id="form_field_' . $field_id . '" name="form_field_' . $field_id . '" class="form-control" value="' . h($_SESSION['software']['forms']['view_submitted_forms']['form_field_' . $field_id] ?? '') . '" />
                    </div>';
                }
            } else {
                $checked = '';
            }

            $output_custom_forms .= '<div class="form-field"><input type="checkbox" name="custom_forms[]" id="custom_form_' . $custom_form['id'] . '" value="' . $custom_form['id'] . '"' . $checked . ' class="check multiselect-checkbox" /><label class="" for="custom_form_' . $custom_form['id'] . '">' . h($custom_form['name']) . ' (' . $number_of_forms . ')</label></div>';
        }

        $output_advanced_filters =
            '<div class="advanced_filters advanced-filter-bar  position-fixed-md"  id="advanced_filters" >
                <div class="p-3 border justify-content-between d-flex flex-wrap header">
                    <p class="m-0"><span class="material-icons pe-1">filter_list</span>' . lang('Filters') . '</p>
                    <a class="btn btn-close " title="' . $output_advanced_filters_label . '" href="view_submitted_forms.php?advanced_filters=' . $output_advanced_filters_value . '" ></a>
                </div>
                <form class="advanced-filter-body p-3 pt-0 disable_shortcut" id="search_advanced" action="view_submitted_forms.php" method="get">
                    <div class="row">
                        <div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('General') . '</h5></div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="reference_code" class="form-label">' . lang('Reference Code') . '</label>
                            <input type="text" id="reference_code" name="reference_code" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['reference_code'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="status" class="form-label">' . lang('Status') . '</label>
                            <select name="status" id="status" class="form-control"><option value="any"' . $status_any_selected . '>[' . lang('Any') . ']</option><option value="incomplete"' . $status_incomplete_selected . '>' . lang('Incomplete') . '</option><option value="complete"' . $status_complete_selected . '>' . lang('Complete') . '</option></select>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="tracking_code" class="form-label">' . lang('Tracking Code') . '</label>
                            <input type="text" id="tracking_code" name="tracking_code" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['tracking_code'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="affiliate_code" class="form-label">' . lang('Affiliate Code') . '</label>
                            <input type="text" id="affiliate_code" name="affiliate_code" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['affiliate_code'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="http_referer" class="form-label">' . lang('Referring URL') . '</label>
                            <input type="text" id="http_referer" name="http_referer" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['http_referer'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="contact_name" class="form-label">' . lang('Contact Name') . '</label>
                            <input type="text" id="contact_name" name="contact_name" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['contact_name'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="member_id" class="form-label">' . h(MEMBER_ID_LABEL) . '</label>
                            <input type="text" id="member_id" name="member_id" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['member_id'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="ip_address" class="form-label">' . lang('IP Address') . '</label>
                            <input type="text" id="ip_address" name="ip_address" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['ip_address'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="username" class="form-label">' . lang('Username') . '</label>
                            <input type="text" id="username" name="username" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['username'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="form_editor" class="form-label">' . lang('Form Editor') . '</label>
                            <input type="text" id="form_editor" name="form_editor" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['form_editor'] ?? '')) . '"/>
                        </div>
                        <div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('Custom Forms') . '</h5></div>
                        <div class="col-12 md:col-12 my-1">
                            <div class="card multiselect-checkbox-container rounded-0">
                                <div class="card-header border-0 bg-reset">
                                    <div class="form-field">
                                        <div class="switch"><input id="multiselect-checkbox-checker-0" class="multiselect-checkbox-checker" type="checkbox"></div>
                                        <label for="multiselect-checkbox-checker-0" class="">' . lang('Select All') . '</label>
                                    </div>
                                </div>
                                <div class="card-body overflow-auto" style="max-height:200px">
                                    <input type="hidden" name="custom_forms" value="" />
                                    ' . $output_custom_forms . '
                                </div>
                            </div>
                        </div>
                        <div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('Custom Form Fields') . '</h5></div>
                        <div class="col-12 md:col-12 my-1">
                            <div class="card rounded-0 bg-1">
                                <div class="card-body overflow-auto" style="max-height:400px">
                                    <div class="row">
                                        ' . $output_form_fields . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('Date Range') . '</h5></div>
                        <fieldset class="col-12" data-pg-date-selects>
                            <legend class="form-label">' . lang('Start') . '</legend>
                            <select class="form-control my-1" name="start_month" aria-label="' . h(lang('Month')) . '">' . select_month(($_SESSION['software']['forms']['view_submitted_forms']['start_month'] ?? '')) . '</select>
                            <div class="input-group input-group-sm">
                                <select class="form-control my-1" name="start_day" aria-label="' . h(lang('Day')) . '">' . select_day(($_SESSION['software']['forms']['view_submitted_forms']['start_day'] ?? '')) . '</select>
                                <select class="form-control my-1" name="start_year" aria-label="' . h(lang('Year')) . '">' . select_year($years, ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? '')) . '</select>
                            </div>
                        </fieldset>
                        <fieldset class="col-12" data-pg-date-selects>
                            <legend class="form-label">' . lang('End') . '</legend>
                            <select class="form-control my-1" name="stop_month" aria-label="' . h(lang('Month')) . '">' . select_month(($_SESSION['software']['forms']['view_submitted_forms']['stop_month'] ?? '')) . '</select>
                            <div class="input-group input-group-sm">
                                <select class="form-control my-1" name="stop_day" aria-label="' . h(lang('Day')) . '">' . select_day(($_SESSION['software']['forms']['view_submitted_forms']['stop_day'] ?? '')) . '</select>
                                <select class="form-control my-1" name="stop_year" aria-label="' . h(lang('Year')) . '">' . select_year($years, ($_SESSION['software']['forms']['view_submitted_forms']['stop_year'] ?? '')) . '</select>
                            </div>
                        </fieldset>
                        ' . get_date_picker_format() . pg_fields_script() . '
                        <div class="col-12 text-center position-sticky my-3" style="bottom:.5rem;">
                            <button type="submit" name="submit_data" value="Update" class="btn btn-solid theme-primary my-1"><i class="material-icons me-3">sync</i>' . lang('Update') . '</button>
                        </div>
                    </div>
                </form>
            </div>';
    }

    // Prepare URL for the create submitted form button.  If the list is currently
    // filtered down to a single custom form, then pass that custom form along, so
    // that the user does not have to select the custom form again.
    $output_add_submitted_form_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_submitted_form.php';

    $selected_custom_form_page_id = '';

    // If advanced filters are on, then the custom form selection lives in custom_forms.
    if (($_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] ?? '') == true) {
        // Only pass the custom form along if exactly one custom form is selected.
        if (
            (is_array(($_SESSION['software']['forms']['view_submitted_forms']['custom_forms'] ?? array())) == true)
            && (count(($_SESSION['software']['forms']['view_submitted_forms']['custom_forms'] ?? array())) == 1)
        ) {
            $selected_custom_form_page_id = $_SESSION['software']['forms']['view_submitted_forms']['custom_forms'][0];
        }

    // Otherwise the custom form selection lives in custom_form.
    } else {
        if (($_SESSION['software']['forms']['view_submitted_forms']['custom_form'] ?? '') != '[All]') {
            $selected_custom_form_page_id = ($_SESSION['software']['forms']['view_submitted_forms']['custom_form'] ?? '');
        }
    }

    // If a single custom form is selected, then deep link straight to its fields.
    if ($selected_custom_form_page_id != '') {
        $output_add_submitted_form_url .= '?page_id=' . urlencode($selected_custom_form_page_id);
    }

    $output_delete_forms_button = '';

    // if mass deletion is allowed, then prepare to output delete forms button
    if (MASS_DELETION == true) {
        $output_delete_forms_button = ' 
        <div class=" btn-group btn-group-sm flex-wrap">
            <button type="submit" name="submit_data" value="Delete Forms" class="btn btn-link pg-link-danger py-0 m-1" data-confirm-content="' . lang('WARNING: All forms that match the filters will be permanently deleted. This includes forms from all result pages that might exist. Please make sure that you perform an update to the filters before you attempt to delete. An update will allow you to see which forms will be deleted before you actually delete them. If you would like to continue with the deletion, please click OK. Otherwise, please click Cancel.') . '"><span class="bi bi-trash3-fill me-1"></span>' . lang(array('string'=>'Delete All Forms') ) . '</button>
        </div>';
    }

    $output .=
    pg_page_shell(
        array(
            'title'=> lang('Forms'),
            'extra classes'=>'form',
            'icon'=>'form', 
            'heading'=>lang('Forms'),
            'heading_description' => lang('All submitted form data that I can view, edit, or export.'),

        )
    ) . '
<main id="content" class="container-fluid">  
    ' . $output_advanced_filters . '
    <script>
        function export_forms() {
            if (document.getElementById("custom_form")) {
                if (document.getElementById("custom_form").value != "[All]") {
                    return true;
                } else {
                    alert("' . lang('You may only export forms from one custom form at a time. Please select only one custom form in the filters and try again.') . '");
                    return false;
                }
            } else {
                var number_of_selected_custom_forms = 0;
            
                for (var i = 0; i < document.forms.length; i++) {
                    for (var j = 0; j < document.forms[i].length; j++) {
                        if ((document.forms[i].elements[j].name == "custom_forms[]") && (document.forms[i].elements[j].checked == true)) {
                            number_of_selected_custom_forms++;
                        }
                    }
                }
                if (number_of_selected_custom_forms == 1) {
                    return true;
                
                } else if (number_of_selected_custom_forms == 0) {
                    alert("' . lang('Please select a custom form in the advanced filters.') . '");
                    return false;
                
                } else {
                    alert("' . lang('You may only export forms from one custom form at a time. Please select only one custom form in the advanced filters and try again.') . '");
                    return false;
                }
            }
        }
    </script>
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
                            <a class="btn btn-sm btn-solid theme-primary m-1" href="' . $output_add_submitted_form_url . '"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <form action="view_submitted_forms.php" method="get" class="disable_shortcut d-inline-block">
                                ' . get_token_field() . '
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <a class="btn btn-link pg-link-secondary py-0 m-1" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/import_submitted_forms.php"><span class="bi bi-box-arrow-in-right me-1"></span>' . lang(array('string'=>'Import') ) . '</a>
                                    <button type="submit" name="submit_data" value="Export Forms" class="btn btn-link pg-link-secondary py-0 m-1" onclick="return export_forms()"><span class="bi bi-file-earmark-arrow-down bi-me-2"></span>' . lang(array('string'=>'Export') ) . '</button>
                                </div>
                                ' . $output_delete_forms_button . '
                            </form>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_submitted_forms.php', $sort_columns, $sort_levels, $sort_default) . '
                            <form id="advanced_filters_form" action="view_submitted_forms.php" method="get" class="search_form disable_shortcut">
                                <input type="hidden" name="filter" value="' . h($filter) . '">
                                <div class="input-group input-group-sm w-auto">
                                    ' . $output_custom_form_selection . '
                                </div>
                            </form>
                            <a class="btn btn-sm btn-text theme-secondary' . $output_advanced_filters_class . '" title="' . $output_advanced_filters_label . '" aria-label="' . $output_advanced_filters_label . '" href="view_submitted_forms.php?advanced_filters=' . $output_advanced_filters_value . '"><i class="bi ' . $advanced_filters_icon . '" aria-hidden="true"></i></a>
                            ' . pg_period_nav('view_submitted_forms.php', ($_SESSION['software']['forms']['view_submitted_forms'] ?? array()), array(
                                'day'   => array($decrease_day, $current_day, $increase_day),
                                'week'  => array($decrease_week, $current_week, $increase_week),
                                'month' => array($decrease_month, $current_month, $increase_month),
                                'year'  => array($decrease_year, $current_year, $increase_year),
                            ), $output_date_range_time) . '
                </nav>
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <form name="form" action="delete_submitted_forms.php" method="post" class="disable_shortcut"> 
                            ' . get_token_field() . '
                            <table class="chart table-hover table " style="width:100%;display:none" data-pg-list-action="submitted_form_list_rows" data-pg-list-params="{}">
                                <thead>
                                    <tr>
                                        <th class="noVis">
                                            <div class="form-field">
                                                <div class="switch"><input class="" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all"></div>
                                            </div>
                                        </th>
                                        <th class="noVis">' . lang('Action') . '</th> 
                                        <th>' . get_column_heading(lang('Custom Form'), $sort_levels, '', '', 'custom_form') . '</th>
                                        <th>' . get_column_heading(lang('Reference Code'), $sort_levels, '', '', 'reference_code') . '</th>
                                        <th>' . get_column_heading(lang('Status'), $sort_levels, '', '', 'status') . '</th>
                                        <th>' . get_column_heading(lang('Contact'), $sort_levels, '', '', 'contact') . '</th>
                                        <th>' . get_column_heading(lang('User'), $sort_levels, '', '', 'user') . '</th>
                                        <th>' . get_column_heading(lang('Submitted'), $sort_levels, '', '', 'submitted') . '</th>
                                        <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                        <button type="button" value="Delete Selected" class="btn mb-1 mt-1 btn-solid theme-danger disabled" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('submitted form(s)')))) . '"><span class="material-icons me-3">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
                                    </div>
                                </div>
                            </nav>
                        </form>
                    </div>
                </div>
            </div>
        </div>

</main>' .
    output_footer();
    print $output;
    $liveform->unmark_errors();
    $liveform->clear_notices();
}