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
validate_forms_access($user);

$liveform = new liveform('view_submitted_forms');

// These are only ever appended to further below, so they have to start out empty.
$output = '';
$output_rows = '';
$sql_data_joins = '';

// The filter is carried through the paging links and the advanced filters form.
$filter = isset($_REQUEST['filter']) ? $_REQUEST['filter'] : '';

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

// get all custom forms (the array will be used in multiple places in this script)
$query = "SELECT
            page.page_id,
            page.page_name,
            page.page_folder as folder_id,
            custom_form_pages.form_name
         FROM page
         LEFT JOIN custom_form_pages ON page.page_id = custom_form_pages.page_id
         WHERE page.page_type = 'custom form'
         ORDER BY custom_form_pages.form_name, page.page_name";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$custom_forms = array();

while ($row = mysqli_fetch_assoc($result)) {
    // if user has access to edit this custom form, then add custom form to array
    if (check_edit_access($row['folder_id']) == true) {
        if ($row['form_name']) {
            $form_name = $row['form_name'];
        } else {
            $form_name = $row['page_name'];
        }

        $custom_forms[] = array(
            'id' => $row['page_id'],
            'name' => $form_name);
    }
}

// if custom form is not set yet, set default to [All]
if (isset($_SESSION['software']['forms']['view_submitted_forms']['custom_form']) == false) {
    $_SESSION['software']['forms']['view_submitted_forms']['custom_form'] = '[All]';
}

// if advanced filters are not set yet, then default them to off
if (isset($_SESSION['software']['forms']['view_submitted_forms']['advanced_filters']) == false) {
    $_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] = false;
}

// if the search query is not set yet, then default it to empty
if (isset($_SESSION['software']['forms']['view_submitted_forms']['query']) == false) {
    $_SESSION['software']['forms']['view_submitted_forms']['query'] = '';
}

// if advanced filters are on and custom forms have not already been set in session, set default for custom forms in advanced filters
if ((($_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] ?? '') == true) && (isset($_SESSION['software']['forms']['view_submitted_forms']['custom_forms']) == false)) {
    // if custom form is set to all, prepare custom forms so all will be checked
    if (($_SESSION['software']['forms']['view_submitted_forms']['custom_form'] ?? '') == '[All]') {
        foreach ($custom_forms as $custom_form) {
            $_SESSION['software']['forms']['view_submitted_forms']['custom_forms'][] = $custom_form['id'];
        }

    // else custom form is not set to all
    } else {
        $_SESSION['software']['forms']['view_submitted_forms']['custom_forms'][] = ($_SESSION['software']['forms']['view_submitted_forms']['custom_form'] ?? '');
    }
}

// if advanced filters value was passed in the query string
if (isset($_REQUEST['advanced_filters']) == true) {
    // if advanced filters should be turned on
    if ($_REQUEST['advanced_filters'] == 'true') {
        $_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] = true;

    // else advanced filters should be turned off
    } else {
        $_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] = false;
    }
}

// if date type is not set yet, set default to last modified
if (isset($_SESSION['software']['forms']['view_submitted_forms']['date_type']) == false) {
    $_SESSION['software']['forms']['view_submitted_forms']['date_type'] = 'last modified';
}

// if date type is last modified
if (($_SESSION['software']['forms']['view_submitted_forms']['date_type'] ?? '') == 'last modified') {
    $date_type_column = 'last_modified_timestamp';
    $date_type_last_modified_selected = ' selected="selected"';

// else date type is submitted
} else {
    $date_type_column = 'submitted_timestamp';
    $date_type_submitted_selected = ' selected="selected"';
}

// find the oldest date type timestamp (this will be used later in a couple of places)
$query = "SELECT MIN($date_type_column) FROM forms";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$oldest_timestamp = $row[0];

// if date has not been set in the session yet, populate start and stop days with default, which is today
if (isset($_SESSION['software']['forms']['view_submitted_forms']['start_month']) == false) {
    $_SESSION['software']['forms']['view_submitted_forms']['start_month'] = date('m', time() - 2678400);
    $_SESSION['software']['forms']['view_submitted_forms']['start_day'] = date('d', time() - 2678400);
    $_SESSION['software']['forms']['view_submitted_forms']['start_year'] = date('Y', time() - 2678400);

    $_SESSION['software']['forms']['view_submitted_forms']['stop_month'] = date('m');
    $_SESSION['software']['forms']['view_submitted_forms']['stop_day'] = date('d');
    $_SESSION['software']['forms']['view_submitted_forms']['stop_year'] = date('Y');
}

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

$decrease_month['new_time'] = mktime(0, 0, 0, ($_SESSION['software']['forms']['view_submitted_forms']['start_month'] ?? '') - 1, 1, ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? ''));
$decrease_month['new_month'] = date('m', $decrease_month['new_time']);
$decrease_month['new_year'] = date('Y', $decrease_month['new_time']);
$decrease_month['start_month'] = $decrease_month['new_month'];
$decrease_month['start_day'] = '01';
$decrease_month['start_year'] = $decrease_month['new_year'];
$decrease_month['stop_month'] = $decrease_month['new_month'];
$decrease_month['stop_day'] = date('t', $decrease_month['new_time']);
$decrease_month['stop_year'] = $decrease_month['new_year'];

$current_month['new_month'] = date('m');
$current_month['new_year'] = date('Y');
$current_month['start_month'] = $current_month['new_month'];
$current_month['start_day'] = '01';
$current_month['start_year'] = $current_month['new_year'];
$current_month['stop_month'] = $current_month['new_month'];
$current_month['stop_day'] = date('t');
$current_month['stop_year'] = $current_month['new_year'];

$increase_month['new_time'] = mktime(0, 0, 0, ($_SESSION['software']['forms']['view_submitted_forms']['start_month'] ?? '') + 1, 1, ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? ''));
$increase_month['new_month'] = date('m', $increase_month['new_time']);
$increase_month['new_year'] = date('Y', $increase_month['new_time']);
$increase_month['start_month'] = $increase_month['new_month'];
$increase_month['start_day'] = '01';
$increase_month['start_year'] = $increase_month['new_year'];
$increase_month['stop_month'] = $increase_month['new_month'];
$increase_month['stop_day'] = date('t', $increase_month['new_time']);
$increase_month['stop_year'] = $increase_month['new_year'];

$decrease_week['start_date_timestamp'] = mktime(0, 0, 0, ($_SESSION['software']['forms']['view_submitted_forms']['start_month'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['start_day'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? ''));
// if start date is a Sunday, use last Sunday (add 12:00:00 to prevent a bug that results in Saturday being returned)
if (date('l', $decrease_week['start_date_timestamp']) == 'Sunday') {
    $decrease_week['new_time_start'] = strtotime('last sunday 12:00:00', $decrease_week['start_date_timestamp']);

// else start date is not a Sunday, so we need to do last sunday twice (add 12:00:00 to prevent a bug that results in Saturday being returned)
} else {
    $decrease_week['new_time_start'] = strtotime('last sunday 12:00:00', $decrease_week['start_date_timestamp']);
    $decrease_week['new_time_start'] = strtotime('last sunday 12:00:00', $decrease_week['new_time_start']);
}
$decrease_week['new_time_stop'] = strtotime('Saturday', $decrease_week['new_time_start']);
$decrease_week['start_month'] = date('m', $decrease_week['new_time_start']);
$decrease_week['start_day'] = date('d', $decrease_week['new_time_start']);
$decrease_week['start_year'] = date('Y', $decrease_week['new_time_start']);
$decrease_week['stop_month'] = date('m', $decrease_week['new_time_stop']);
$decrease_week['stop_day'] = date('d', $decrease_week['new_time_stop']);
$decrease_week['stop_year'] = date('Y', $decrease_week['new_time_stop']);

// if today is Sunday
if (date('l') == 'Sunday') {
    $current_week['new_time_start'] = strtotime('Sunday');
} else {
    $current_week['new_time_start'] = strtotime('last Sunday');
}
$current_week['new_time_stop'] = strtotime('Saturday', $current_week['new_time_start']);
$current_week['start_month'] = date('m', $current_week['new_time_start']);
$current_week['start_day'] = date('d', $current_week['new_time_start']);
$current_week['start_year'] = date('Y', $current_week['new_time_start']);
$current_week['stop_month'] = date('m', $current_week['new_time_stop']);
$current_week['stop_day'] = date('d', $current_week['new_time_stop']);
$current_week['stop_year'] = date('Y', $current_week['new_time_stop']);

$increase_week['start_date_timestamp'] = mktime(0, 0, 0, ($_SESSION['software']['forms']['view_submitted_forms']['start_month'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['start_day'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? ''));
// if start date is a Sunday
if (date('l', $increase_week['start_date_timestamp']) == 'Sunday') {
    $increase_week['new_time_start'] = strtotime('2 Sunday', $increase_week['start_date_timestamp']);
} else {
    $increase_week['new_time_start'] = strtotime('Sunday', $increase_week['start_date_timestamp']);
}
$increase_week['new_time_stop'] = strtotime('Saturday', $increase_week['new_time_start']);
$increase_week['start_month'] = date('m', $increase_week['new_time_start']);
$increase_week['start_day'] = date('d', $increase_week['new_time_start']);
$increase_week['start_year'] = date('Y', $increase_week['new_time_start']);
$increase_week['stop_month'] = date('m', $increase_week['new_time_stop']);
$increase_week['stop_day'] = date('d', $increase_week['new_time_stop']);
$increase_week['stop_year'] = date('Y', $increase_week['new_time_stop']);

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

// get timestamps for start and stop dates
$start_timestamp = mktime(0, 0, 0, ($_SESSION['software']['forms']['view_submitted_forms']['start_month'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['start_day'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? ''));
$stop_timestamp = mktime(23, 59, 59, ($_SESSION['software']['forms']['view_submitted_forms']['stop_month'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['stop_day'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['stop_year'] ?? ''));

$where = "WHERE (forms.$date_type_column >= $start_timestamp) AND (forms.$date_type_column <= $stop_timestamp)";

// Output start date range time
$output_date_range_time = h(get_month_name_from_number(($_SESSION['software']['forms']['view_submitted_forms']['start_month'] ?? '')) . ' ' . ($_SESSION['software']['forms']['view_submitted_forms']['start_day'] ?? '') . ', ' . ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? ''));
$output_date_range_time .= ' - ';

// Output end date range time
$output_date_range_time .= h(get_month_name_from_number(($_SESSION['software']['forms']['view_submitted_forms']['stop_month'] ?? '')) . ' ' . ($_SESSION['software']['forms']['view_submitted_forms']['stop_day'] ?? '') . ', ' . ($_SESSION['software']['forms']['view_submitted_forms']['stop_year'] ?? ''));

// if advanced filters are on, prepare SQL for checked custom forms
if (($_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] ?? '') == true) {
    // if at least one custom form is checked
    if (is_array(($_SESSION['software']['forms']['view_submitted_forms']['custom_forms'] ?? array())) == true) {
        foreach (($_SESSION['software']['forms']['view_submitted_forms']['custom_forms'] ?? array()) as $custom_form) {
            // if this is not the first custom form, then add an OR before SQL
            if ($where_custom_forms) {
                $where_custom_forms .= " OR";
            }

            $where_custom_forms .= " (forms.page_id = '" . escape($custom_form) . "')";

            // get fields for custom form, so we can prepare SQL for data joins
            $query = "SELECT form_fields.id
                     FROM form_fields
                     WHERE form_fields.page_id = '" . escape($custom_form) . "'
                     ORDER BY form_fields.sort_order";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            $conditions_for_form = '';

            while ($row = mysqli_fetch_assoc($result)) {
                $field_id = $row['id'];

                // if a filter value has been entered for this field, then we need to add a join and a where condition
                if ($_SESSION['software']['forms']['view_submitted_forms']['form_field_' . $field_id]) {
                    $sql_data_joins .= "LEFT JOIN form_data AS form_data_" . $field_id . " ON ((forms.id = form_data_" . $field_id . ".form_id) AND (form_data_" . $field_id . ".form_field_id = '" . $field_id . "') AND (form_data_" . $field_id . ".data LIKE '%" . escape($_SESSION['software']['forms']['view_submitted_forms']['form_field_' . $field_id]) . "%')) ";

                    // if data conditions have already been set for this form, add an AND
                    if ($conditions_for_form) {
                        $conditions_for_form .= " AND";
                    }

                    $conditions_for_form .= " (form_data_" . $field_id . ".data IS NOT NULL)";
                }
            }

            // if there was at least one condition for this form
            if ($conditions_for_form) {
                // if conditions have already been added for all forms, add an OR
                if ($conditions_for_all_forms) {
                    $conditions_for_all_forms .= " OR";
                }

                // update conditions for all forms to include conditions for this form
                $conditions_for_all_forms .= " ($conditions_for_form)";
            }
        }

        $where .= " AND ($where_custom_forms)";

        // if there are data conditions, add conditions to where clause
        if ($conditions_for_all_forms) {
            $where .= " AND ($conditions_for_all_forms)";
        }

    // else no custom forms are checked, so use SQL that will result in no forms being found
    } else {
        $where .= " AND (0 = 1)";
    }

// else advanced filters are off, so use custom form picklist
} else {
    // if user has not choosen [All] filter by custom form selected
    if (($_SESSION['software']['forms']['view_submitted_forms']['custom_form'] ?? '') != '[All]') {
        $where .= " AND (forms.page_id = '" . e(($_SESSION['software']['forms']['view_submitted_forms']['custom_form'] ?? '')) . "')";
    }
}

if (($_SESSION['software']['forms']['view_submitted_forms']['query'] ?? '')) {
    $where .=
        " AND (LOWER(CONCAT_WS(',',
        forms.reference_code,
        forms.address_name,
        forms.tracking_code,
        forms.affiliate_code,
        forms.http_referer,
        INET_NTOA(forms.ip_address),
        custom_form_pages.form_name,
        CONCAT(contacts.first_name, ' ', contacts.last_name),
        submitted_user.user_username,
        form_editor_user.user_username)) LIKE '%" . escape(mb_strtolower(($_SESSION['software']['forms']['view_submitted_forms']['query'] ?? ''))) . "%')";
}

// if advanced filters are on, prepare SQL
if (($_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] ?? '') == true) {
    if (($_SESSION['software']['forms']['view_submitted_forms']['reference_code'] ?? '')) {$where .= " AND (forms.reference_code LIKE '%" . escape(($_SESSION['software']['forms']['view_submitted_forms']['reference_code'] ?? '')) . "%')";}

    if (($_SESSION['software']['forms']['view_submitted_forms']['status'] ?? '')) {
        if (($_SESSION['software']['forms']['view_submitted_forms']['status'] ?? '') == 'incomplete') {
            $where .= " AND (forms.complete = '0')";
        } else if (($_SESSION['software']['forms']['view_submitted_forms']['status'] ?? '') == 'complete') {
            $where .= " AND (forms.complete = '1')";
        }
    }

    if (($_SESSION['software']['forms']['view_submitted_forms']['tracking_code'] ?? '')) {$where .= " AND (forms.tracking_code LIKE '%" . escape(($_SESSION['software']['forms']['view_submitted_forms']['tracking_code'] ?? '')) . "%')";}
    if (($_SESSION['software']['forms']['view_submitted_forms']['affiliate_code'] ?? '')) {$where .= " AND (forms.affiliate_code LIKE '%" . escape(($_SESSION['software']['forms']['view_submitted_forms']['affiliate_code'] ?? '')) . "%')";}
    if (($_SESSION['software']['forms']['view_submitted_forms']['http_referer'] ?? '')) {$where .= " AND (forms.http_referer LIKE '%" . escape(($_SESSION['software']['forms']['view_submitted_forms']['http_referer'] ?? '')) . "%')";}
    if (($_SESSION['software']['forms']['view_submitted_forms']['ip_address'] ?? '')) {$where .= " AND (INET_NTOA(forms.ip_address) LIKE '%" . escape(($_SESSION['software']['forms']['view_submitted_forms']['ip_address'] ?? '')) . "%')";}
    if (($_SESSION['software']['forms']['view_submitted_forms']['contact_name'] ?? '')) {$where .= " AND (CONCAT(contacts.first_name, ' ', contacts.last_name) LIKE '%" . escape(($_SESSION['software']['forms']['view_submitted_forms']['contact_name'] ?? '')) . "%')";}
    if (($_SESSION['software']['forms']['view_submitted_forms']['username'] ?? '')) {$where .= " AND (submitted_user.user_username LIKE '%" . escape(($_SESSION['software']['forms']['view_submitted_forms']['username'] ?? '')) . "%')";}
    if (($_SESSION['software']['forms']['view_submitted_forms']['form_editor'] ?? '')) {$where .= " AND (form_editor_user.user_username LIKE '%" . e(($_SESSION['software']['forms']['view_submitted_forms']['form_editor'] ?? '')) . "%')";}
    if (($_SESSION['software']['forms']['view_submitted_forms']['member_id'] ?? '')) {$where .= " AND (contacts.member_id LIKE '%" . escape(($_SESSION['software']['forms']['view_submitted_forms']['member_id'] ?? '')) . "%')";}
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['forms']['view_submitted_forms']['sort']) == false) {
    $_SESSION['software']['forms']['view_submitted_forms']['sort'] = '';
}

switch (($_SESSION['software']['forms']['view_submitted_forms']['sort'] ?? '')) {
    case 'Custom Form':
        $sort_column = 'custom_form_pages.form_name';
        break;

    case 'Status':
        $sort_column = 'forms.complete';
        break;

    case 'Reference Code':
        $sort_column = 'forms.reference_code';
        break;

    case 'Contact':
        $sort_column = 'contacts.last_name ' . escape(($_SESSION['software']['forms']['view_submitted_forms']['order'] ?? '')) . ', contacts.first_name';
        break;

    case 'User':
        $sort_column = 'submitted_user.user_username';
        break;

    case 'Submitted':
        $sort_column = 'forms.submitted_timestamp';
        break;

    case 'Last Modified':
        $sort_column = 'forms.last_modified_timestamp';
        break;

    default:
        $sort_column = 'forms.last_modified_timestamp';
        $_SESSION['software']['forms']['view_submitted_forms']['sort'] = 'Last Modified';
        $_SESSION['software']['forms']['view_submitted_forms']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['forms']['view_submitted_forms']['order']) == false) {
    $_SESSION['software']['forms']['view_submitted_forms']['order'] = 'asc';
}

// if user requested to export forms, export forms
if (($_GET['submit_data'] ?? '') == 'Export Forms') {
    if (($_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] ?? '') == true) {
        $custom_form_page_id = $_SESSION['software']['forms']['view_submitted_forms']['custom_forms'][0];
    } else {
        $custom_form_page_id = ($_SESSION['software']['forms']['view_submitted_forms']['custom_form'] ?? '');
    }

    // force download dialog
    header("Content-type: text/csv");
    header("Content-disposition: attachment; filename=forms.csv");

    echo '"form_name","reference_code","status","address_name","tracking_code","affiliate_code","http_referer","ip_address","submitted","last_modified","member_id","form_editor"';

    // check if custom form is a quiz
    $query =
        "SELECT quiz
        FROM custom_form_pages
        WHERE page_id = '" . escape($custom_form_page_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $quiz = $row['quiz'];

    // if custom form is a quiz, then include quiz score column
    if ($quiz == 1) {
        print ',"quiz_score"';
    }

    // get fields for custom form
    $query = "SELECT name
             FROM form_fields
             WHERE (page_id = '" . escape($custom_form_page_id) . "') AND (type != 'information') AND (type != 'file upload')
             ORDER BY sort_order";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        // replace quotation mark with two quotation marks
        $column_name = str_replace('"', '""', $row['name']);

        print ',"' . $column_name . '"';
    }

    print "\n";

    // get all forms that match filters
    $query =
        "SELECT
            forms.id,
            page.page_folder as folder_id,
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
        FROM forms
        LEFT JOIN page ON forms.page_id = page.page_id
        LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
        LEFT JOIN contacts ON forms.contact_id = contacts.id
        LEFT JOIN user as submitted_user ON forms.user_id = submitted_user.user_id
        LEFT JOIN user AS form_editor_user ON forms.form_editor_user_id = form_editor_user.user_id
        $sql_data_joins
        $where
        ORDER BY $sort_column " . escape(($_SESSION['software']['forms']['view_submitted_forms']['order'] ?? ''));
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $forms = array();

    while($row = mysqli_fetch_assoc($result)) {
        // if user has access to edit this form's custom form then add form to forms array
        if (check_edit_access($row['folder_id']) == true) {
            $forms[] = $row;
        }
    }

    $number_of_forms = 0;

    // If the date format is month and then day, then use that format.
    if (DATE_FORMAT == 'month_day') {
        $month_and_day_format = 'n/j';

    // Otherwise the date format is day and then month, so use that format.
    } else {
        $month_and_day_format = 'j/n';
    }

    foreach ($forms as $form) {
        $form_name = str_replace('"', '""', $form['form_name']);
        $quiz_score = $form['quiz_score'];
        $reference_code = str_replace('"', '""', $form['reference_code']);

        if ($form['complete']) {
            $status = 'Complete';
        } else {
            $status = 'Incomplete';
        }

        $address_name = str_replace('"', '""', $form['address_name']);
        $tracking_code = str_replace('"', '""', $form['tracking_code']);
        $affiliate_code = str_replace('"', '""', $form['affiliate_code']);
        $http_referer = str_replace('"', '""', $form['http_referer']);
        $submitted = date($month_and_day_format . '/Y g:i:s A T', $form['submitted_timestamp']);
        $last_modified = date($month_and_day_format . '/Y g:i:s A T', $form['last_modified_timestamp']);
        $member_id = str_replace('"', '""', $form['member_id']);
        $form_editor = str_replace('"', '""', $form['form_editor']);

        $ip_address = $form['ip_address'];
        
        // If the IP address is 0.0.0.0, then we don't know the IP address, so set the value to empty string.
        if ($ip_address == '0.0.0.0') {
            $ip_address = '';
        }

        print '"' . $form_name . '","' . $reference_code . '","' . $status . '", "' . $address_name . '","' . $tracking_code . '","' . $affiliate_code . '","' . $http_referer . '","' . $ip_address . '","' . $submitted . '","' . $last_modified . '","' . $member_id . '","' . $form_editor . '"';

        // if custom form is a quiz, then include quiz score
        if ($quiz == 1) {
            print ',"' . $quiz_score . '"';
        }

        // get data for all fields for this form
        $query = "SELECT
                    form_fields.id,
                    form_data.data
                 FROM form_fields
                 LEFT JOIN form_data ON ((form_fields.id = form_data.form_field_id) AND (form_data.form_id = '" . $form['id'] . "'))
                 WHERE (form_fields.page_id = '" . escape($custom_form_page_id) . "') AND (form_fields.type != 'information') AND (form_fields.type != 'file upload')
                 ORDER BY form_fields.sort_order";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

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
            $data = str_replace('"', '""', $data);
            print ',"' . $data . '"';
        }

        print "\n";

        $number_of_forms++;
    }

    log_activity(lang(array('string'=>'{var:1} submitted form(s) were exported','vars'=>$number_of_forms)), $_SESSION['sessionusername']);

// if mass deletion is allowed and user requested to delete forms, delete forms
} elseif ((MASS_DELETION == true) && (($_GET['submit_data'] ?? '') == 'Delete Forms')) {
    // get all forms that match filters
    $query = "SELECT
                forms.id,
                page.page_folder as folder_id,
                forms.page_id,
                custom_form_pages.form_name
             FROM forms
             LEFT JOIN page ON forms.page_id = page.page_id
             LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
             LEFT JOIN contacts ON forms.contact_id = contacts.id
             LEFT JOIN user as submitted_user ON forms.user_id = submitted_user.user_id
             LEFT JOIN user AS form_editor_user ON forms.form_editor_user_id = form_editor_user.user_id
             $sql_data_joins
             $where";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $forms = array();

    while ($row = mysqli_fetch_assoc($result)) {
        // if user has access to edit this form's custom form then user has access to delete form
        if (check_edit_access($row['folder_id']) == true) {
            $forms[] = $row;
        }
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
                 WHERE (form_data.form_id = '" . $form['id'] . "') AND (form_data.file_id > 0)";
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
            }
        }

        // delete form
        $query = "DELETE FROM forms WHERE id = '" . $form['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // delete form data
        $query = "DELETE FROM form_data WHERE (form_id = '" . $form['id'] . "') AND (form_id != '0')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete views for this submitted form that the form view directory feature uses
        pg_sfv_delete_views('submitted_form_id', $form['id']);

        $custom_forms_for_log[$form['page_id']] = $form['form_name'];

        $number_of_forms++;
    }

    // prepare list of custom forms for log

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
        log_activity(lang(array('string'=>'{var:1} submitted form(s) from custom form(s) ({var:2}) were deleted','vars'=>array(number_format($number_of_forms),$custom_form_list ) )) , $_SESSION['sessionusername']);

        $liveform->add_notice( lang(array('string'=>'{var:1} form(s) were deleted','vars'=>number_format($number_of_forms))) );
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

    // If a screen was passed and it is a positive integer, then use it.
    // These checks are necessary in order to avoid SQL errors below for a bogus screen value.
    if (
        isset($_REQUEST['screen'])
        and $_REQUEST['screen']
        and is_numeric($_REQUEST['screen'])
        and $_REQUEST['screen'] > 0
        and $_REQUEST['screen'] == round($_REQUEST['screen'])
    ) {
        $screen = (int) $_REQUEST['screen'];

    // Otherwise, use the default, which is the first screen.
    } else {
        $screen = 1;
    }

    // Get number of all forms
    $query = "SELECT id
             FROM forms";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $all_forms = 0;

    // Count all forms.
    while ($row = mysqli_fetch_assoc($result)) {
        $all_forms++;
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
                 WHERE page_id = '" . $custom_form['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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

    // get all forms that match filters
    $query = "SELECT
                forms.id,
                page.page_folder as folder_id,
                forms.complete,
                custom_form_pages.form_name,
                forms.reference_code,
                contacts.id as contact_id,
                CONCAT(contacts.first_name, ' ', contacts.last_name) as contact_name,
                forms.user_id,
                submitted_user.user_username as submitted_username,
                forms.submitted_timestamp,
                last_modified_user.user_username as last_modified_username,
                forms.last_modified_timestamp
             FROM forms
             LEFT JOIN page ON forms.page_id = page.page_id
             LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
             LEFT JOIN contacts ON forms.contact_id = contacts.id
             LEFT JOIN user as submitted_user ON forms.user_id = submitted_user.user_id
             LEFT JOIN user as last_modified_user ON forms.last_modified_user_id = last_modified_user.user_id
             LEFT JOIN user AS form_editor_user ON forms.form_editor_user_id = form_editor_user.user_id
             $sql_data_joins
             $where
             ORDER BY $sort_column " . escape(($_SESSION['software']['forms']['view_submitted_forms']['order'] ?? ''));

    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $forms = array();

    while ($row = mysqli_fetch_assoc($result)) {
        // if user has access to edit this form's custom form then add form to forms array
        if (check_edit_access($row['folder_id']) == true) {
            $forms[] = $row;
        }
    }

    // define the maximum number of results
    $max = 100;

    $number_of_results = count($forms);

    // get number of screens
    $number_of_screens = ceil($number_of_results / $max);

    // if there are more than one screen
    if ($number_of_screens > 1) {

        $output_screen_links .= '
            <nav class="mt-3 navigation " aria-label="data pagination"> 
                <ul class="pagination pagination-sm flex-wrap justify-content-center">';
        // build Previous button if necessary
        $previous = $screen - 1;
        // if previous screen is greater than zero, output previous link
        if ($previous > 0) {
            $output_screen_links .= '<li class="page-item mt-1 mb-1"><a class="page-link" href="view_submitted_forms.php?filter=' . h($filter) . '&screen=' . $previous . $output_filter_for_links . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
        }else{
            $output_screen_links .= '<li class="page-item mt-1 mb-1 disabled"><a class="page-link" href="#!" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
        }

        // build HTML output for links to screens
        for ($i = 1; $i <= $number_of_screens; $i++) {
            // if this number is the current screen, then select option
            if ($i == $screen) {
                $active = 'active"';
            // else this number is not the current screen, so do not select option
            } else {
                $active = '';
            }
            $output_screen_links .= '<li class="page-item mt-1 mb-1 ' . $active . '"><a class="page-link " href="view_submitted_forms.php?filter=' . h($filter) . '&screen=' . $i . $output_filter_for_links . '">' . $i . '</a></li>';
        }
        // build Next button if necessary
        $next = $screen + 1;
        // if next screen is less than or equal to the total number of screens, output next link
        if ($next <= $number_of_screens) {
            $output_screen_links .= '<li class="page-item mt-1 mb-1"><a class="page-link" href="view_submitted_forms.php?filter=' . h($filter) . '&screen=' . $next . $output_filter_for_links . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
        }else{
            $output_screen_links .= '<li class="page-item mt-1 mb-1 disabled"><a class="page-link" href="#!" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
        }
        $output_screen_links .= '</ul></nav>';
    }

    // determine where result set should start
    $start = $screen * $max - $max;

    // determine where result set should end
    $end = $start + $max - 1;

    // get the value of the last index of the forms array
    $last_index = count($forms) - 1;

    // if the end if past the last index of the array, set the end to the last index of the array
    if ($end > $last_index) {
        $end = $last_index;
    }

    // loop through all forms for this screen
    for ($key = $start; $key <= $end; $key++) {
        $id = $forms[$key]['id'];
        $complete = $forms[$key]['complete'];
        $form_name = $forms[$key]['form_name'];
        $reference_code = $forms[$key]['reference_code'];
        $contact_id = $forms[$key]['contact_id'];
        $contact_name = $forms[$key]['contact_name'];
        $submitted_username = $forms[$key]['submitted_username'];
        $submitted_timestamp = $forms[$key]['submitted_timestamp'];
        $last_modified_username = $forms[$key]['last_modified_username'];
        $last_modified_timestamp = $forms[$key]['last_modified_timestamp'];

        if ($complete) {
            $status = '<span class="badge bg-success">' . lang('Complete') . '</span>';
        } else {
            $status = '<span class="badge bg-warning text-dark">' . lang('Incomplete') . '</span>';
        }

        if ($contact_id) {
            if (trim($contact_name) == '') {
                $contact_name = '[' . lang('No Name') . ']';
            }
        } else {
            $contact_name = '';
        }

        // if user has access to contacts, then prepare link to contact
        if (validate_contacts_access($user, $only_return = true) == true) {
            $contact_name = '<a class="link-secondary" href="edit_contact.php?id=' . $contact_id . '">' . h($contact_name) . '</a>';
        } else {
            $contact_name = h($contact_name);
        }

        $username = $submitted_username;

        if (!$submitted_username) {
            $submitted_username = '[' . lang('Unknown') . ']';
        }

        if (!$last_modified_username) {
            $last_modified_username = '[' . lang('Unknown') . ']';
        }

        $output_link_url = 'edit_submitted_form.php?id=' . $id;

        $output_rows .=
        '<tr>
            <td class="select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="forms[]" value="' . $id . '" class="checkbox" /></td>
            <td class="align-middle text-start action-buttons">
                <button type="button" class="btn-data-control m-1 btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td class="chart_label">' . h($form_name) . '</td>
            <td class="align-middle">' . h($reference_code) . '</td>
            <td class="align-middle">' . $status . '</td>
            <td class="align-middle">' . $contact_name . '</td>
            <td class="align-middle">' . h($username) . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $submitted_timestamp)) . ' ' . h($submitted_username) . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $last_modified_timestamp)) .' ' . h($last_modified_username) . '</td>
        </tr>';
    }

    // if the advanced filters are off
    if (($_SESSION['software']['forms']['view_submitted_forms']['advanced_filters'] ?? '') == false) {
        
        $output_custom_form_selection = '
            <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Custom Form') . '" for="custom_form">format_list_bulleted</label>
            <select id="custom_form" name="custom_form" class="form-select mt-1 mb-1" title="' . lang('Content that viewed') . '" onchange="submit_form(\'advanced_filters_form\')">' . $output_custom_form_options . '</select>';
        $output_advanced_filters_value = 'true';
        $output_advanced_filters_label = lang('Add Advanced Filters');
        $output_advanced_filters = '';
        $advanced_filters_icon = 'filter_list';
        $output_advanced_filters_class = 'btn-primary';

      
    // else the advanced filters are on
    } else {
       
        $output_custom_form_selection = '
        <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Custom Form') . '" for="custom_form_fake">format_list_bulleted</label>
        <select id="custom_form_fake" name="custom_form_fake" disabled class="form-select mt-1 mb-1 disabled" title="' . lang('Content that viewed') . '"><option value="">' . lang('Disabled') . '</option></select>';
        $output_advanced_filters_value = 'false';
        $output_advanced_filters_label = lang('Remove Advanced Filters');
        $advanced_filters_icon = 'filter_list_off';
        $output_advanced_filters_class = 'btn-danger';
        



        // Prepare selection for status field.
        if (($_SESSION['software']['forms']['view_submitted_forms']['status'] ?? '') == 'any') {
            $status_any_selected = ' selected="selected"';

        } elseif (($_SESSION['software']['forms']['view_submitted_forms']['status'] ?? '') == 'incomplete') {
            $status_incomplete_selected = ' selected="selected"';

        } elseif (($_SESSION['software']['forms']['view_submitted_forms']['status'] ?? '') == 'complete') {
            $status_complete_selected = ' selected="selected"';
        }

        foreach ($custom_forms as $custom_form) {
            // get number of forms for custom form
            $query = "SELECT COUNT(id)
                     FROM forms
                     WHERE page_id = '" . $custom_form['id'] . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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
                         WHERE (form_fields.page_id = '" . $custom_form['id'] . "') AND (form_fields.type != 'information') AND (form_fields.type != 'file upload')
                         ORDER BY form_fields.sort_order";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                while ($row = mysqli_fetch_assoc($result)) {
                    $field_id = $row['id'];
                    $field_name = $row['name'];

                    $output_form_fields .=
                    '<div class="col-12 col-sm-6 col-md-12 my-1">
                        <label for="form_field_' . $field_id . '" class="form-label">' . h($custom_form['name']) . ' :: ' . h($field_name) . '</label>
                        <input type="text" id="form_field_' . $field_id . '" name="form_field_' . $field_id . '" class="form-control" value="' . h($_SESSION['software']['forms']['view_submitted_forms']['form_field_' . $field_id]) . '" />
                    </div>';
                }
            } else {
                $checked = '';
            }

            $output_custom_forms .= '<div class="form-check"><input type="checkbox" name="custom_forms[]" id="custom_form_' . $custom_form['id'] . '" value="' . $custom_form['id'] . '"' . $checked . ' class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="custom_form_' . $custom_form['id'] . '">' . h($custom_form['name']) . ' (' . $number_of_forms . ')</label></div>';
        }

        $output_advanced_filters =
            '<div class="advanced_filters advanced-filter-bar  position-fixed-md"  id="advanced_filters" >
                <div class="p-2 border justify-content-between d-flex flex-wrap header">
                    <p class="m-0"><span class="material-icons pe-1">filter_list</span>' . lang('Filters') . '</p>
                    <a class="btn btn-close " title="' . $output_advanced_filters_label . '" href="view_submitted_forms.php?advanced_filters=' . $output_advanced_filters_value . '" ></a>
                </div>
                <form class="advanced-filter-body p-2 pt-0 disable_shortcut" id="search_advanced" action="view_submitted_forms.php" method="get">
                    <div class="row">
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('General') . '</h5></div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="reference_code" class="form-label">' . lang('Reference Code') . '</label>
                            <input type="text" id="reference_code" name="reference_code" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['reference_code'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="status" class="form-label">' . lang('Status') . '</label>
                            <select name="status" id="status" class="form-select"><option value="any"' . $status_any_selected . '>[' . lang('Any') . ']</option><option value="incomplete"' . $status_incomplete_selected . '>' . lang('Incomplete') . '</option><option value="complete"' . $status_complete_selected . '>' . lang('Complete') . '</option></select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="tracking_code" class="form-label">' . lang('Tracking Code') . '</label>
                            <input type="text" id="tracking_code" name="tracking_code" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['tracking_code'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="affiliate_code" class="form-label">' . lang('Affiliate Code') . '</label>
                            <input type="text" id="affiliate_code" name="affiliate_code" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['affiliate_code'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="http_referer" class="form-label">' . lang('Referring URL') . '</label>
                            <input type="text" id="http_referer" name="http_referer" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['http_referer'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="contact_name" class="form-label">' . lang('Contact Name') . '</label>
                            <input type="text" id="contact_name" name="contact_name" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['contact_name'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="member_id" class="form-label">' . h(MEMBER_ID_LABEL) . '</label>
                            <input type="text" id="member_id" name="member_id" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['member_id'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="ip_address" class="form-label">' . lang('IP Address') . '</label>
                            <input type="text" id="ip_address" name="ip_address" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['ip_address'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="username" class="form-label">' . lang('Username') . '</label>
                            <input type="text" id="username" name="username" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['username'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="form_editor" class="form-label">' . lang('Form Editor') . '</label>
                            <input type="text" id="form_editor" name="form_editor" class="form-control" value="' . h(($_SESSION['software']['forms']['view_submitted_forms']['form_editor'] ?? '')) . '"/>
                        </div>
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Custom Forms') . '</h5></div>
                        <div class="col-12 col-md-12 my-1">
                            <div class="card multiselect-checkbox-container rounded-0">
                                <div class="card-header border-0 bg-reset">
                                    <div class="form-check form-switch">
                                        <input id="multiselect-checkbox-checker-0" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                                        <label for="multiselect-checkbox-checker-0" class="form-check-label">' . lang('Select All') . '</label>
                                    </div>
                                </div>
                                <div class="card-body overflow-auto" style="max-height:200px">
                                    <input type="hidden" name="custom_forms" value="" />
                                    ' . $output_custom_forms . '
                                </div>
                            </div>
                        </div>
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Custom Form Fields') . '</h5></div>
                        <div class="col-12 col-md-12 my-1">
                            <div class="card rounded-0 bg-body-tertiary">
                                <div class="card-body overflow-auto" style="max-height:400px">
                                    <div class="row">
                                        ' . $output_form_fields . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Date Range') . '</h5></div>
                        <div class="col-12">
                            <label class="form-label">' . lang('From') . '</label>
                            <select class="form-select my-1" name="start_month">' . select_month(($_SESSION['software']['forms']['view_submitted_forms']['start_month'] ?? '')) . '</select>
                            <div class="input-group input-group-sm">
                                <select class="form-select my-1" name="start_day">' . select_day(($_SESSION['software']['forms']['view_submitted_forms']['start_day'] ?? '')) . '</select>
                                <select class="form-select my-1" name="start_year">' . select_year($years, ($_SESSION['software']['forms']['view_submitted_forms']['start_year'] ?? '')) . '</select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">' . lang('To') . '</label>
                            <select class="form-select my-1" name="stop_month">' . select_month(($_SESSION['software']['forms']['view_submitted_forms']['stop_month'] ?? '')) . '</select>
                            <div class="input-group input-group-sm">
                                <select class="form-select my-1" name="stop_day">' . select_day(($_SESSION['software']['forms']['view_submitted_forms']['stop_day'] ?? '')) . '</select>
                                <select class="form-select my-1" name="stop_year">' . select_year($years, ($_SESSION['software']['forms']['view_submitted_forms']['stop_year'] ?? '')) . '</select>
                            </div>
                        </div>
                        <div class="col-12 text-center position-sticky my-2" style="bottom:.5rem;">
                            <button type="submit" name="submit_data" value="Update" data-loading-content="' . lang('Updating') . '" class="btn btn-primary my-1"><i class="material-icons me-2">sync</i>' . lang('Update') . '</button>
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
            <button type="submit" name="submit_data" value="Delete Forms" class="btn btn-link link-danger py-0 m-1" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang('WARNING: All forms that match the filters will be permanently deleted. This includes forms from all result pages that might exist. Please make sure that you perform an update to the filters before you attempt to delete. An update will allow you to see which forms will be deleted before you actually delete them. If you would like to continue with the deletion, please click OK. Otherwise, please click Cancel.') . '"><span class="bi bi-trash3-fill me-1"></span>' . lang(array('string'=>'Delete All Forms') ) . '</button>
        </div>';
    }

    $output .=
    pg_page_shell(
        array(
            'title'=> lang('Forms'),
            'extra classes'=>'form',
            'icon'=>'form', 
            'heading'=>lang('Forms'),

        )
    ) . '  
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
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-8 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('All submitted form data that I can view, edit, or export.') . '" title="' . lang('My Submitted Forms') . '">' . lang('My Submitted Forms') . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1" href="' . $output_add_submitted_form_url . '" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <form action="view_submitted_forms.php" method="get" class="disable_shortcut d-inline-block">
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <a class="btn btn-link link-secondary py-0 m-1" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/import_submitted_forms.php"><span class="bi bi-box-arrow-in-right me-1"></span>' . lang(array('string'=>'Import') ) . '</a>
                                    <button type="submit" name="submit_data" value="Export Forms" class="btn btn-link link-secondary py-0 m-1" onclick="return export_forms()"><span class="bi bi-file-earmark-arrow-down bi-me-2"></span>' . lang(array('string'=>'Export') ) . '</button>
                                </div>
                                ' . $output_delete_forms_button . '
                            </form>
                        </nav>
                    </div>
                    <div class="col-12 col-sm-12 col-md-6 col-xl-4 ">
                        <div class="row justify-content-center justify-content-md-end">
                            <form id="advanced_filters_form" action="view_submitted_forms.php" method="get" class="search_form disable_shortcut col-auto">
                                <input type="hidden" name="filter" value="' . h($filter) . '">
                                <div class="input-group input-group-sm">
                                    <a class="btn btn-sm my-1 ' . $output_advanced_filters_class . '" data-loading-content=" " title="' . $output_advanced_filters_label . '" href="view_submitted_forms.php?advanced_filters=' . $output_advanced_filters_value . '" ><i class="material-icons">'. $advanced_filters_icon . '</i></a>
                                    ' . $output_custom_form_selection . '
                                </div>
                                <div class="row justify-content-center justify-content-md-end" >
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_submitted_forms.php?start_month=' . $decrease_year['start_month'] . '&start_day=' . $decrease_year['start_day'] . '&start_year=' . $decrease_year['start_year'] . '&stop_month=' . $decrease_year['stop_month'] . '&stop_day=' . $decrease_year['stop_day'] . '&stop_year=' . $decrease_year['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_submitted_forms.php?start_month=' . $current_year['start_month'] . '&start_day=' . $current_year['start_day'] . '&start_year=' . $current_year['start_year'] . '&stop_month=' . $current_year['stop_month'] . '&stop_day=' . $current_year['stop_day'] . '&stop_year=' . $current_year['stop_year'] . '">' . lang('Year') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_submitted_forms.php?start_month=' . $increase_year['start_month'] . '&start_day=' . $increase_year['start_day'] . '&start_year=' . $increase_year['start_year'] . '&stop_month=' . $increase_year['stop_month'] . '&stop_day=' . $increase_year['stop_day'] . '&stop_year=' . $increase_year['stop_year'] . '">></a>
                                    </div>
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_submitted_forms.php?start_month=' . $decrease_month['start_month'] . '&start_day=' . $decrease_month['start_day'] . '&start_year=' . $decrease_month['start_year'] . '&stop_month=' . $decrease_month['stop_month'] . '&stop_day=' . $decrease_month['stop_day'] . '&stop_year=' . $decrease_month['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_submitted_forms.php?start_month=' . $current_month['start_month'] . '&start_day=' . $current_month['start_day'] . '&start_year=' . $current_month['start_year'] . '&stop_month=' . $current_month['stop_month'] . '&stop_day=' . $current_month['stop_day'] . '&stop_year=' . $current_month['stop_year'] . '">' . lang('Month') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_submitted_forms.php?start_month=' . $increase_month['start_month'] . '&start_day=' . $increase_month['start_day'] . '&start_year=' . $increase_month['start_year'] . '&stop_month=' . $increase_month['stop_month'] . '&stop_day=' . $increase_month['stop_day'] . '&stop_year=' . $increase_month['stop_year'] . '">></a>
                                    </div>
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">    
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_submitted_forms.php?start_month=' . $decrease_week['start_month'] . '&start_day=' . $decrease_week['start_day'] . '&start_year=' . $decrease_week['start_year'] . '&stop_month=' . $decrease_week['stop_month'] . '&stop_day=' . $decrease_week['stop_day'] . '&stop_year=' . $decrease_week['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_submitted_forms.php?start_month=' . $current_week['start_month'] . '&start_day=' . $current_week['start_day'] . '&start_year=' . $current_week['start_year'] . '&stop_month=' . $current_week['stop_month'] . '&stop_day=' . $current_week['stop_day'] . '&stop_year=' . $current_week['stop_year'] . '">' . lang('Week') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_submitted_forms.php?start_month=' . $increase_week['start_month'] . '&start_day=' . $increase_week['start_day'] . '&start_year=' . $increase_week['start_year'] . '&stop_month=' . $increase_week['stop_month'] . '&stop_day=' . $increase_week['stop_day'] . '&stop_year=' . $increase_week['stop_year'] . '">></a>
                                    </div>
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">    
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_submitted_forms.php?start_month=' . $decrease_day['start_month'] . '&start_day=' . $decrease_day['start_day'] . '&start_year=' . $decrease_day['start_year'] . '&stop_month=' . $decrease_day['stop_month'] . '&stop_day=' . $decrease_day['stop_day'] . '&stop_year=' . $decrease_day['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_submitted_forms.php?start_month=' . $current_day['start_month'] . '&start_day=' . $current_day['start_day'] . '&start_year=' . $current_day['start_year'] . '&stop_month=' . $current_day['stop_month'] . '&stop_day=' . $current_day['stop_day'] . '&stop_year=' . $current_day['stop_year'] . '">' . lang('Day') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_submitted_forms.php?start_month=' . $increase_day['start_month'] . '&start_day=' . $increase_day['start_day'] . '&start_year=' . $increase_day['start_year'] . '&stop_month=' . $increase_day['stop_month'] . '&stop_day=' . $increase_day['stop_day'] . '&stop_year=' . $increase_day['stop_year'] . '">></a>
                                    </div>    
                                </div>
                                <p class="text-center text-md-end p-0 m-0">
                                    <span class="badge text-dark fw-light border-2">    ' . $output_date_range_time . '</span>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <form name="form" action="delete_submitted_forms.php" method="post" class="disable_shortcut"> 
                            ' . get_token_field() . '
                            <table class="chart table-hover table " style="width:100%;display:none">
                                <thead>
                                    <tr>
                                        <th class="noVis">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                                            </div>
                                        </th>
                                        <th class="noVis">' . lang('Action') . '</th> 
                                        <th>' . get_column_heading(lang('Custom Form'), ($_SESSION['software']['forms']['view_submitted_forms']['sort'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Reference Code'), ($_SESSION['software']['forms']['view_submitted_forms']['sort'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Status'), ($_SESSION['software']['forms']['view_submitted_forms']['sort'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Contact'), ($_SESSION['software']['forms']['view_submitted_forms']['sort'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('User'), ($_SESSION['software']['forms']['view_submitted_forms']['sort'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Submitted'), ($_SESSION['software']['forms']['view_submitted_forms']['sort'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['forms']['view_submitted_forms']['sort'] ?? ''), ($_SESSION['software']['forms']['view_submitted_forms']['order'] ?? '')) . '</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . $output_rows . '
                                </tbody>
                            </table>
                            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                                <div class="container">
                                    <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                        <button type="button" value="Delete Selected" class="btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('submitted form(s)')))) . '"><span class="material-icons me-2">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
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