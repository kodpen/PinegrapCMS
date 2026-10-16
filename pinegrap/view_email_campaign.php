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

// A reference code that is missing or sent as an array (?r[]=x) counts as not
// given.
$_GET['r'] = (isset($_GET['r']) && is_string($_GET['r'])) ? $_GET['r'] : '';

// if there is a reference code, check to see if it exists
if ($_GET['r']) {
    // get e-mail campaign id and contact id based on reference id
    $query =
        "SELECT
            email_address,
            email_campaign_id,
            contact_id
        FROM email_recipients
        WHERE reference_code = '" . escape($_GET['r']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    // An unknown reference code leaves these empty and ends on the "could not
    // be found" error below.
    $email_address = $row['email_address'] ?? '';
    $email_campaign_id = $row['email_campaign_id'] ?? '';
    $contact_id = $row['contact_id'] ?? '';
    
// else, there was no reference code so validate the user
} else {
    $user = validate_user();
    validate_email_access($user);

    // Stop early when the screen is opened without an existing record (stale
    // link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
    // "not found" screen instead of PHP notices from the unchecked row below or
    // an error page.
    $id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
    if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM email_campaigns WHERE id = '" . $id . "'") < 1)) {
        output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
    }

    $email_campaign_id = escape($_GET['id']);
}

// if there is not an email campaign id, then output error
if (!$email_campaign_id) {
    output_error(lang('The e-mail campaign could not be found. The reference code might be invalid or missing.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

// get e-mail campaign information
$query =
    "SELECT
        type,
        action,
        action_item_id,
        calendar_event_recurrence_number,
        format,
        body,
        created_user_id,
        created_timestamp
    FROM email_campaigns
    WHERE id = '" . $email_campaign_id . "'";
$result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
$row = mysqli_fetch_assoc($result);

$type = $row['type'];
$action = $row['action'];
$action_item_id = $row['action_item_id'];
$calendar_event_recurrence_number = $row['calendar_event_recurrence_number'];
$format = $row['format'];
$body = $row['body'];
$created_user_id = $row['created_user_id'];
$created_timestamp = $row['created_timestamp'];

// if the e-mail campaign has a plain text format, then output error (this script is only for HTML e-mails)
if ($format == 'plain_text') {
    output_error(lang('The e-mail campaign cannot be viewed through this feature because it has a plain text format.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

// if user has a user role, is not the creator of the e-mail campaign and there is not a reference code, then output error
if (!$_GET['r'] && ($user['role'] == 3) && ($created_user_id != $user['id'])) {
    log_activity(lang('access denied to view e-mail campaign because user is not the creator of the e-mail campaign'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

// if there is a reference code
if ($_GET['r']) {
    // Create an array that will store the fields for variables that need to be replaced.
    $fields = array();

    // setup variables
    $mail_merge_first_name = '';
    $mail_merge_last_name = '';
    $mail_merge_nickname = '';
    $mail_merge_salutation = '';
    $mail_merge_suffix = '';
    
    // get contact information for email recipient
    $query =
        "SELECT
            first_name,
            last_name,
            nickname,
            salutation,
            suffix
         FROM contacts
         WHERE id = '" . $contact_id . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // A recipient whose contact was deleted since the send has no name to merge.
    $row = mysqli_fetch_assoc($result) ?: array('first_name' => '', 'last_name' => '', 'nickname' => '', 'salutation' => '', 'suffix' => '');

    $first_name = trim($row['first_name']);
    $last_name = trim($row['last_name']);
    
    // if there is a first name in the database, update the variable
    if ($row['first_name'] != '') {
        $mail_merge_first_name = ' ' . trim($row['first_name']);
    }
    
    // if there is a last name in the database, update the variable
    if ($row['last_name'] != '') {
        $mail_merge_last_name = ' ' . trim($row['last_name']);
    }
    
    // if there is a nickname in the database, update the variable
    if ($row['nickname'] != '') {
        $mail_merge_nickname = trim($row['nickname']);
    }
    
    // if there is a salutation in the database, update the variable
    if ($row['salutation'] != '') {
        $mail_merge_salutation = trim($row['salutation']);
    }
    
    // if there is a suffix in the database, update the variable
    if ($row['suffix'] != '') {
        $mail_merge_suffix = ', ' . trim($row['suffix']);
    }
    
    // if there is a nickname for the current email recipient, use that for the dynamic data
    if ($mail_merge_nickname != '') {
        $mail_merge_dynamic_data = trim($mail_merge_nickname);
        
    // else, there was not a nickname, so display the users full name
    } else {
        $mail_merge_dynamic_data = trim($mail_merge_salutation . $mail_merge_first_name . $mail_merge_last_name . $mail_merge_suffix);
    }
    
    // Add name field so that variable is replaced.
    $fields[] = array(
        'name' => 'name',
        'data' => $mail_merge_dynamic_data,
        'type' => '');

    // Add first_name field so that variable is replaced.
    $fields[] = array(
        'name' => 'first_name',
        'data' => $first_name,
        'type' => '');

    // Add last_name field so that variable is replaced.
    $fields[] = array(
        'name' => 'last_name',
        'data' => $last_name,
        'type' => '');

    // If this is an auto email campaign, then add fields for that type of campaign.
    if ($type == 'automatic') {
        // Add field for action date and time.
        $fields[] = array(
            'name' => 'action_date_and_time',
            'data' => date('Y-m-d H:i:s', $created_timestamp),
            'type' => 'date and time');

        // If this email campaign was created due to a calendar event being reserved, then add fields for that.
        if ($action == 'calendar_event_reserved') {
            $calendar_event = get_calendar_event($action_item_id, $calendar_event_recurrence_number);

            // Add field for calendar event date and time range.
            $fields[] = array(
                'name' => 'calendar_event_date_and_time_range',
                'data' => $calendar_event['date_and_time_range'],
                'type' => '');

            // Add field for calendar event start date and time.
            $fields[] = array(
                'name' => 'calendar_event_start_date_and_time',
                'data' => $calendar_event['start_date_and_time'],
                'type' => 'date and time');

            // Add field for calendar event end date and time.
            $fields[] = array(
                'name' => 'calendar_event_end_date_and_time',
                'data' => $calendar_event['end_date_and_time'],
                'type' => 'date and time');
        }
    }

    // Replace variables in body (e.g. ^^name^^).
    $body = replace_variables(array(
        'content' => $body,
        'fields' => $fields,
        'format' => $format));
    
    // replace reference code tags with reference code
    $body = str_replace('<reference_code></reference_code>', h($_GET['r']), $body);
    
    // replace email_address_id with encoded email address string
    $body = preg_replace('/<email_address_id><\/email_address_id>/', pg_email_preferences_placeholder_value($email_address), $body);
}

// output e-mail campaign body
print $body;
?>