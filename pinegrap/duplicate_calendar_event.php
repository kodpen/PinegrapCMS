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
validate_calendars_access($user);

validate_token_field();

// A copy needs an existing calendar event. An id that is missing, unknown or
// sent as an array (?id[]=x) stops on "not found": the copy was made from an
// empty row, or escape() failed on the array with an error page.
$id = is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM calendar_events WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// if user does not have access to calendar event, then output error
if (validate_calendar_event_access($_GET['id']) == false) {
    log_activity(lang('access denied to edit calendar event because user does not have access to calendar that the calendar event is in'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

// get original calendar event's info
$query =
    "SELECT
        name,
        published,
        unpublish_days,
        short_description,
        full_description,
        notes,
        all_day,
        start_time,
        end_time,
        show_start_time,
        show_end_time,
        recurrence,
        recurrence_number,
        recurrence_type,
        recurrence_day_sun,
        recurrence_day_mon,
        recurrence_day_tue,
        recurrence_day_wed,
        recurrence_day_thu,
        recurrence_day_fri,
        recurrence_day_sat,
        recurrence_month_type,
        location,
        reservations,
        separate_reservations,
        limit_reservations,
        number_of_initial_spots,
        no_remaining_spots_message,
        reserve_button_label,
        product_id,
        next_page_id
    FROM calendar_events WHERE id = ?";
$result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
$calendar_event = mysqli_fetch_assoc($result);

// if the event is published and the user does not have rights to publish calendar events then do not publish new event
if (($calendar_event['published'] == 1) && ($user['role'] == 3) && ($user['publish_calendar_events'] == FALSE)) {
    $calendar_event['published'] = 0;
}

// create new calendar event
$query =
    "INSERT INTO calendar_events (
        name,
        published,
        unpublish_days,
        short_description,
        full_description,
        notes,
        all_day,
        start_time,
        end_time,
        show_start_time,
        show_end_time,
        recurrence,
        recurrence_number,
        recurrence_type,
        recurrence_day_sun,
        recurrence_day_mon,
        recurrence_day_tue,
        recurrence_day_wed,
        recurrence_day_thu,
        recurrence_day_fri,
        recurrence_day_sat,
        recurrence_month_type,
        location,
        reservations,
        separate_reservations,
        limit_reservations,
        number_of_initial_spots,
        no_remaining_spots_message,
        reserve_button_label,
        product_id,
        next_page_id,
        created_user_id,
        created_timestamp,
        last_modified_user_id,
        last_modified_timestamp)
    VALUES (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        UNIX_TIMESTAMP(),
        ?,
        UNIX_TIMESTAMP())";
$result = db_q($query, array((string) $calendar_event['name'], (string) $calendar_event['published'], (string) $calendar_event['unpublish_days'], (string) $calendar_event['short_description'], (string) $calendar_event['full_description'], (string) $calendar_event['notes'], (string) $calendar_event['all_day'], (string) $calendar_event['start_time'], (string) $calendar_event['end_time'], (string) $calendar_event['show_start_time'], (string) $calendar_event['show_end_time'], (string) $calendar_event['recurrence'], (string) $calendar_event['recurrence_number'], (string) $calendar_event['recurrence_type'], (string) $calendar_event['recurrence_day_sun'], (string) $calendar_event['recurrence_day_mon'], (string) $calendar_event['recurrence_day_tue'], (string) $calendar_event['recurrence_day_wed'], (string) $calendar_event['recurrence_day_thu'], (string) $calendar_event['recurrence_day_fri'], (string) $calendar_event['recurrence_day_sat'], (string) $calendar_event['recurrence_month_type'], (string) $calendar_event['location'], (string) $calendar_event['reservations'], (string) $calendar_event['separate_reservations'], (string) $calendar_event['limit_reservations'], (string) $calendar_event['number_of_initial_spots'], (string) $calendar_event['no_remaining_spots_message'], (string) $calendar_event['reserve_button_label'], (string) $calendar_event['product_id'], (string) $calendar_event['next_page_id'], (string) $user['id'], (string) $user['id'])) or output_error('Query failed.');

$new_calendar_event_id = mysqli_insert_id(db::$con);

// get all calendars that original event is in
$query = "SELECT calendar_id as id FROM calendar_events_calendars_xref WHERE calendar_event_id = ?";
$result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');

$calendars = array();

// loop through all calendars in order to build array
while ($row = mysqli_fetch_assoc($result)) {
    $calendars[] = $row;
}

// loop through all calendars in order to assign new event to them
foreach ($calendars as $calendar) {
    // if the user has access to this calendar then assign new calendar event to this calendar
    if (validate_calendar_access($calendar['id']) == TRUE) {
        $query =
            "INSERT INTO calendar_events_calendars_xref (
                calendar_event_id,
                calendar_id)
            VALUES (
                ?,
                ?)";
        $result = db_q($query, array((string) $new_calendar_event_id, (string) $calendar['id'])) or output_error('Query failed.');
    }
}

// get all calendar event exceptions for the original event in order to assign them to the new event
$query = "SELECT recurrence_number FROM calendar_event_exceptions WHERE calendar_event_id = ?";
$result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');

$calendar_event_exceptions = array();

// loop through all exceptions in order to build array
while ($row = mysqli_fetch_assoc($result)) {
    $calendar_event_exceptions[] = $row;
}

// loop through all exceptions in order to assign exceptions to new event
foreach ($calendar_event_exceptions as $calendar_event_exception) {
    $query =
        "INSERT INTO calendar_event_exceptions (
            calendar_event_id,
            recurrence_number)
        VALUES (
            ?,
            ?)";
    $result = db_q($query, array((string) $new_calendar_event_id, (string) $calendar_event_exception['recurrence_number'])) or output_error('Query failed.');
}

log_activity(lang(array('string'=>'{var:1} ({var:2}) was duplicated','vars'=>array(lang('event'), $calendar_event['name']) )), $_SESSION['sessionusername']);


include_once('liveform.class.php');
$liveform = new liveform('edit_calendar_event', $new_calendar_event_id);

// add notice that the calendar event has been duplicated
$liveform->add_notice(lang(array('string'=>'The {var:1} has been duplicated, and you are now editing the duplicate.','vars'=>lang('event') )) );

header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_calendar_event.php?id=' . $new_calendar_event_id);
?>