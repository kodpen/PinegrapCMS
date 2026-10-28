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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');
$user = validate_user();
validate_calendars_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM calendar_events WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// The recurring instance being edited: the edit button on a calendar event
// page adds ?recurrence_number=N and the form posts it back. Opened from the
// calendars list it is not given, so it stays empty; an array counts as not
// given. Any other value is read as a number, the same way the calendar
// event page reads it.
pg_request_single_values(array('recurrence_number'));

$requested_recurrence_number = '';

if (isset($_GET['recurrence_number']) && ($_GET['recurrence_number'] !== '')) {
    $requested_recurrence_number = (string) max(0, (int) $_GET['recurrence_number']);
}

// if user does not have access to calendar event, then output error
if (validate_calendar_event_access($_REQUEST['id']) == false) {
    log_activity(lang('access denied to edit calendar event because user does not have access to calendar that the calendar event is in'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

// if user is a basic user and user does not have access to publish calendar events then check if calendar event is published
// in order to check if the user should be able to edit this calendar event
if (($user['role'] == 3) && ($user['publish_calendar_events'] == FALSE)) {
    // check to see if the calendar event has been published
    $query = "SELECT published FROM calendar_events WHERE id = ?";
    $result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    // if the event has been published then log and output error
    if ($row['published'] == '1') {
        log_activity(lang('access denied to edit published calendar event because user does not have publish rights to calendar events'), $_SESSION['sessionusername']);
        output_error(lang('Access denied.'));
    }
}

include_once('liveform.class.php');
$liveform = new liveform('edit_calendar_event', $_REQUEST['id']);

// if the form has not been submitted
if (!$_POST) {
    // Every calendar and location, for the checkboxes and to tick the event's own.
    $choices = pg_calendar_event_form_choices();
    $calendars = $choices['calendars'];
    $calendar_event_locations = $choices['calendar_event_locations'];
    
    // if edit calendar event screen has not been submitted already, pre-populate fields with data
    if ($liveform->field_in_session('name') == FALSE) {
        // get calendar event data
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
            FROM calendar_events
            WHERE id = ?";
        $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        $liveform->assign_field_value('name', $row['name']);
        $liveform->assign_field_value('published', $row['published']);
        $liveform->assign_field_value('unpublish_days', $row['unpublish_days']);
        $liveform->assign_field_value('short_description', $row['short_description']);
        $liveform->assign_field_value('full_description', prepare_rich_text_editor_content_for_output($row['full_description']));
        $liveform->assign_field_value('notes', prepare_rich_text_editor_content_for_output($row['notes']));
        $liveform->assign_field_value('all_day', $row['all_day']);
        $liveform->assign_field_value('start_time', prepare_form_data_for_output($row['start_time'], 'date and time', $prepare_for_html = false));
        $liveform->assign_field_value('end_time', prepare_form_data_for_output($row['end_time'], 'date and time', $prepare_for_html = false));
        $liveform->assign_field_value('show_start_time', $row['show_start_time']);
        $liveform->assign_field_value('show_end_time', $row['show_end_time']);
        $liveform->assign_field_value('recurrence', $row['recurrence']);

        // If the recurrence number is 0, then set a default value of 1.
        if ($row['recurrence_number'] == 0) {
            $liveform->assign_field_value('total_recurrence_number', 1);
        } else {
            $liveform->assign_field_value('total_recurrence_number', $row['recurrence_number']);
        }

        $liveform->assign_field_value('recurrence_type', $row['recurrence_type']);
        $liveform->assign_field_value('recurrence_day_sun', $row['recurrence_day_sun']);
        $liveform->assign_field_value('recurrence_day_mon', $row['recurrence_day_mon']);
        $liveform->assign_field_value('recurrence_day_tue', $row['recurrence_day_tue']);
        $liveform->assign_field_value('recurrence_day_wed', $row['recurrence_day_wed']);
        $liveform->assign_field_value('recurrence_day_thu', $row['recurrence_day_thu']);
        $liveform->assign_field_value('recurrence_day_fri', $row['recurrence_day_fri']);
        $liveform->assign_field_value('recurrence_day_sat', $row['recurrence_day_sat']);
        $liveform->assign_field_value('recurrence_month_type', $row['recurrence_month_type']);
        $liveform->assign_field_value('location', $row['location']);
        $liveform->assign_field_value('reservations', $row['reservations']);
        $liveform->assign_field_value('separate_reservations', $row['separate_reservations']);
        $liveform->assign_field_value('limit_reservations', $row['limit_reservations']);
        $liveform->assign_field_value('number_of_initial_spots', $row['number_of_initial_spots']);
        $liveform->assign_field_value('no_remaining_spots_message', prepare_rich_text_editor_content_for_output($row['no_remaining_spots_message']));
        $liveform->assign_field_value('reserve_button_label', $row['reserve_button_label']);
        $liveform->assign_field_value('product_id', $row['product_id']);
        $liveform->assign_field_value('next_page_id', $row['next_page_id']);
        
        // get all calendars that this event is in
        $query =
            "SELECT calendar_id
            FROM calendar_events_calendars_xref
            WHERE calendar_event_id = ?";
        $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
        
        $selected_calendars = array();
        
        // loop through all selected calendars in order to build array
        while ($row = mysqli_fetch_assoc($result)) {
            $selected_calendars[] = $row['calendar_id'];
        }
        
        // loop through all calendars in order to prepare to check selected calendars
        foreach ($calendars as $calendar) {
            // if user has access to calendar and calendar event is in this calendar, then prepare to check calendar checkbox
            if ((validate_calendar_access($calendar['id']) == true) && (in_array($calendar['id'], $selected_calendars) == true)) {
                $liveform->assign_field_value('calendar_' . $calendar['id'], 1);
            }
        }
        
        // get all calendars event locations that this event is at
        $query =
            "SELECT calendar_event_location_id
            FROM calendar_events_calendar_event_locations_xref
            WHERE calendar_event_id = ?";
        $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
        
        $selected_calendar_event_locations = array();
        
        // loop through all selected calendar event locations in order to build array
        while ($row = mysqli_fetch_assoc($result)) {
            $selected_calendar_event_locations[] = $row['calendar_event_location_id'];
        }
        
        // loop through all calendar event locations in order to prepare to check selected calendar event locations
        foreach ($calendar_event_locations as $calendar_event_location) {
            // if calendar event is at this location, then prepare to check calendar checkbox
            if (in_array($calendar_event_location['id'], $selected_calendar_event_locations) == true) {
                $liveform->assign_field_value('calendar_event_location_' . $calendar_event_location['id'], 1);
            }
        }
        
        // if reservations are separated for each recurring instance then prepare to look for remaining spots for this recurrence number
        if ($liveform->get_field_value('separate_reservations') == 1) {
            $recurrence_number = $requested_recurrence_number;
            
        // else reservations are not separated for each recurring instance so look for remaining spots for the 0 recurrence number
        } else {
            $recurrence_number = 0;
        }
        
        // get number of remaining spots for this calendar event and recurrence number
        $query =
            "SELECT number_of_remaining_spots
            FROM remaining_reservation_spots
            WHERE
                (calendar_event_id = ?)
                AND (recurrence_number = ?)";
        $result = db_q($query, array((string) $_GET['id'], (string) $recurrence_number)) or output_error('Query failed.');
        
        // if a remaining spots record was found then use it to populate the remaining spots
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $liveform->assign_field_value('number_of_remaining_spots', $row['number_of_remaining_spots']);
            
        // else a remaining spots record was not found, so use initial spots
        } else {
            $liveform->assign_field_value('number_of_remaining_spots', $liveform->get_field_value('number_of_initial_spots'));
        }
    }

    // Where it was talked about in the workspace, and the tasks about it.
    $output_workspace_button = '';

    if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
        $output_workspace_button = ws_record_button($user, 'calendar_event', (int) $_GET['id'], (string) db_value("SELECT name FROM calendar_events WHERE id = '" . (int) $_GET['id'] . "'"), 'btn btn-link pg-link-secondary py-0 mb-3');
    }

    print
     
    pg_page_shell(
        array(
            'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Event'))),
            'extra classes'=>'calendar',
            'icon'=>'calendar', 
            'heading'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Event'))),
            'heading_description' => lang('View, edit, or publish this calendar event.'),
            'cancel'=>array('enable'=>'true','url'=>'view_calendars.php'),
        
            'breadcrumb' => array(array('label' => lang('View Calendars'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/calendars.php'), array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('Event'))))),
        )
    ) . '
<main id="content" class="container-fluid">
    ' . get_date_time_picker_format() . '
    ' . get_wysiwyg_editor_code(array('full_description', 'notes', 'no_remaining_spots_message')) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">

                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link pg-link-secondary py-0 mb-3 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/duplicate_calendar_event.php?id=' . h(escape_javascript($_GET['id'])) . get_token_query_string_field() . '"><span class="material-icons me-1">control_point_duplicate</span>' . lang('Duplicate') . '</a>
                                ' . $output_workspace_button . '
                            </div>
                        </nav>
                    </div>
                </div>
                <form name="form" action="edit_calendar_event.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'send_to', 'value'=>($_GET['send_to'] ?? ''))) . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'recurrence_number', 'value'=>$requested_recurrence_number)) . '
                    ' . pg_calendar_event_form_cards($liveform, $user, $calendars, $calendar_event_locations, $id) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('Event')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
    
    $liveform->remove_form();

// else the form has been submitted
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    // if calendar event was selected for delete
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        // get calendar event name for log
        $query = "SELECT name FROM calendar_events WHERE id = ?";
        $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $calendar_event_name = $row['name'];
        
        // delete calendar event
        $query = "DELETE FROM calendar_events WHERE id = ?";
        $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
        
        // delete calendar event exceptions if any exist
        $query = "DELETE FROM calendar_event_exceptions WHERE calendar_event_id = ?";
        $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
        
        // delete calendar event cross reference if any exist
        $query = "DELETE FROM calendar_events_calendars_xref WHERE calendar_event_id = ?";
        $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
        
        // delete calendar event & calendar event locations cross reference if any exist
        $query = "DELETE FROM calendar_events_calendar_event_locations_xref WHERE calendar_event_id = ?";
        $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
        
        // delete remaining spots data if any exists
        $query = "DELETE FROM remaining_reservation_spots WHERE calendar_event_id = ?";
        $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');

        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('event'), $calendar_event_name) )), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_calendars = new liveform('calendars');
        $liveform_calendars->add_notice(lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Event') )));
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/calendars.php');
        
        
        
    // else calendar event was not selected for delete
    } else {
        
        $checked = pg_calendar_event_form_validate($liveform, $liveform->get_field_value('id'));
        $start_time = $checked['start_time'];
        $end_time = $checked['end_time'];
        $calendars = $checked['calendars'];
        $calendar_event_locations = $checked['calendar_event_locations'];
        
        // if there is an error, forward user back to edit calendar event screen
        if ($liveform->check_form_errors() == true) {
            $query_string_recurrence_number = '';
            
            // if there is a recurrence number, then add recurrence number to query string
            if ($liveform->get_field_value('recurrence_number') != '') {
                $query_string_recurrence_number = '&recurrence_number=' . $liveform->get_field_value('recurrence_number');
            }
            
            $query_string_send_to = '';
            
            // if there is a send to, then add send to to query string
            if ($liveform->get_field_value('send_to') != '') {
                $query_string_send_to = '&send_to=' . $liveform->get_field_value('send_to');
            }
            
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_calendar_event.php?id=' . $liveform->get_field_value('id') . $query_string_recurrence_number . $query_string_send_to);
            exit();
        }

        // Check to see if the start or end date were modified
        $query =
            "SELECT 
                UNIX_TIMESTAMP(start_time) as start_time,
                UNIX_TIMESTAMP(end_time) as end_time
            FROM calendar_events
            WHERE id = ?";
        $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
        
        $row = mysqli_fetch_assoc($result);
        $new_start_time = strtotime(prepare_form_data_for_input($start_time, 'date and time'));
        $new_end_time = strtotime(prepare_form_data_for_input($end_time, 'date and time'));
        
        // If the start or end time changed, then delete all exceptions for the calendar event (if any exist)
        if (($new_start_time != $row['start_time'])
            || ($new_end_time != $row['end_time'])) {
            // delete calendar event exceptions (if any)
            $query = "DELETE FROM calendar_event_exceptions WHERE calendar_event_id = ?";
            $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
        }

        // If recurrence was disabled then set recurrence number to 0,
        // in order to prevent issues with logic that outputs calendar.
        if ($liveform->get_field_value('recurrence') == 0) {
            $liveform->assign_field_value('total_recurrence_number', 0);
        }
        
        // if notes has real content, then use notes content
        if ((strip_tags(escape($liveform->get_field_value('notes'))) != '')
            && (strip_tags(escape($liveform->get_field_value('notes'))) != '&nbsp;')) {
                $notes = prepare_rich_text_editor_content_for_input($liveform->get_field_value('notes'));
                
        // else notes does not have real content, so set to empty string
        } else {
            $notes = '';
        }

        $sql_update_publish_columns = '';
        $publish_values = array();
        
        // If the user has access to publish calendar events then allow
        // publish fields to be updated
        if (($user['role'] < 3) || ($user['publish_calendar_events'] == 'yes')) {
            $sql_update_publish_columns =
                "published = ?,
                unpublish_days = ?,";
            $publish_values = array((string) $liveform->get('published'), (string) $liveform->get('unpublish_days'));
        }
        
        // update calendar event
        $query =
            "UPDATE calendar_events
            SET
                name = ?,
                $sql_update_publish_columns
                short_description = ?,
                full_description = ?,
                notes = ?,
                all_day = ?,
                start_time = ?,
                end_time = ?,
                show_start_time = ?,
                show_end_time = ?,
                recurrence = ?,
                recurrence_number = ?,
                recurrence_type = ?,
                recurrence_day_sun = ?,
                recurrence_day_mon = ?,
                recurrence_day_tue = ?,
                recurrence_day_wed = ?,
                recurrence_day_thu = ?,
                recurrence_day_fri = ?,
                recurrence_day_sat = ?,
                recurrence_month_type = ?,
                location = ?,
                reservations = ?,
                separate_reservations = ?,
                limit_reservations = ?,
                number_of_initial_spots = ?,
                no_remaining_spots_message = ?,
                reserve_button_label = ?,
                product_id = ?,
                next_page_id = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        $result = db_q($query, array_merge(
            array((string) $liveform->get_field_value('name')),
            $publish_values,
            array(
                (string) $liveform->get_field_value('short_description'),
                (string) prepare_rich_text_editor_content_for_input($liveform->get_field_value('full_description')),
                (string) $notes,
                (string) $liveform->get_field_value('all_day'),
                (string) prepare_form_data_for_input($start_time, 'date and time'),
                (string) prepare_form_data_for_input($end_time, 'date and time'),
                (string) $liveform->get_field_value('show_start_time'),
                (string) $liveform->get_field_value('show_end_time'),
                (string) $liveform->get_field_value('recurrence'),
                (string) $liveform->get_field_value('total_recurrence_number'),
                (string) $liveform->get_field_value('recurrence_type'),
                (string) $liveform->get_field_value('recurrence_day_sun'),
                (string) $liveform->get_field_value('recurrence_day_mon'),
                (string) $liveform->get_field_value('recurrence_day_tue'),
                (string) $liveform->get_field_value('recurrence_day_wed'),
                (string) $liveform->get_field_value('recurrence_day_thu'),
                (string) $liveform->get_field_value('recurrence_day_fri'),
                (string) $liveform->get_field_value('recurrence_day_sat'),
                (string) $liveform->get_field_value('recurrence_month_type'),
                (string) $liveform->get_field_value('location'),
                (string) $liveform->get_field_value('reservations'),
                (string) $liveform->get_field_value('separate_reservations'),
                (string) $liveform->get_field_value('limit_reservations'),
                (string) $liveform->get_field_value('number_of_initial_spots'),
                (string) prepare_rich_text_editor_content_for_input($liveform->get_field_value('no_remaining_spots_message')),
                (string) $liveform->get_field_value('reserve_button_label'),
                (string) $liveform->get_field_value('product_id'),
                (string) $liveform->get_field_value('next_page_id'),
                (string) $user['id'],
                (string) $liveform->get_field_value('id'))
        )) or output_error('Query failed.');
        
        foreach ($calendars as $calendar) {
            if (validate_calendar_access($calendar['id']) == true) {
                if ($liveform->get_field_value('calendar_' . $calendar['id']) == 1) {
                    // Check to see if the event is already added to the current calendar.
                    $query =
                        "SELECT calendar_event_id
                        FROM calendar_events_calendars_xref
                        WHERE 
                            (calendar_event_id = ?) AND 
                            (calendar_id = ?)";
                    $result = db_q($query, array((string) $liveform->get_field_value('id'), (string) $calendar['id'])) or output_error('Query failed.');
                    
                    if (mysqli_num_rows($result) == 0) {
                        $query =
                            "INSERT INTO calendar_events_calendars_xref (
                                calendar_event_id,
                                calendar_id)
                            VALUES (
                                ?,
                                ?)";
                        $result = db_q($query, array((string) $liveform->get_field_value('id'), (string) $calendar['id'])) or output_error('Query failed.');
                    }
                // Else, the current calendar was not selected for the calendar event, so remove it from the database.
                } else {
                    $query =
                        "DELETE FROM calendar_events_calendars_xref
                        WHERE
                            (calendar_event_id = ?)
                            AND (calendar_id = ?)";
                    $result = db_q($query, array((string) $liveform->get_field_value('id'), (string) $calendar['id'])) or output_error('Query failed.');
                }
            }
        }
        
        // Foreach location
        foreach ($calendar_event_locations as $calendar_event_location) {
            // if a location was selected, add it to the locations xref table.
            if ($liveform->get_field_value('calendar_event_location_' . $calendar_event_location['id']) == 1) {
                // Check to see if the location is already added to the current calendar event
                $query =
                    "SELECT
                        calendar_event_id
                    FROM calendar_events_calendar_event_locations_xref
                    WHERE 
                        (calendar_event_id = ?) AND 
                        (calendar_event_location_id = ?)";
                $result = db_q($query, array((string) $liveform->get_field_value('id'), (string) $calendar_event_location['id'])) or output_error('Query failed.');
                // If not, then add it!
                if (mysqli_num_rows($result) == 0) {
                    $query =
                        "INSERT INTO calendar_events_calendar_event_locations_xref (
                            calendar_event_id,
                            calendar_event_location_id)
                        VALUES (
                            ?,
                            ?)";
                    $result = db_q($query, array((string) $liveform->get_field_value('id'), (string) $calendar_event_location['id'])) or output_error('Query failed.');
                }
            // Else, the location was not selected so remove it if it was previously.
            } else {
                $query =
                    "DELETE FROM calendar_events_calendar_event_locations_xref
                    WHERE
                        (calendar_event_id = ?)
                        AND (calendar_event_location_id = ?)";
                $result = db_q($query, array((string) $liveform->get_field_value('id'), (string) $calendar_event_location['id'])) or output_error('Query failed.');
            }
        }
        
        // if reservations are enabled and limit reservations is enabled then update remaining spots
        if (
            ($liveform->get_field_value('reservations') == 1)
            && ($liveform->get_field_value('limit_reservations') == 1)
        ) {
            // if this is not a recurring event or if separate reservations is disabled, then set recurrence number to 0
            if (
                ($liveform->get_field_value('recurrence') == 0)
                || ($liveform->get_field_value('separate_reservations') == 0)
            ) {
                $liveform->assign_field_value('recurrence_number', 0);
            }
            
            // delete existing remaining spots data if any exists
            $query =
                "DELETE FROM remaining_reservation_spots
                WHERE
                    (calendar_event_id = ?)
                    AND (recurrence_number = ?)";
            $result = db_q($query, array((string) $liveform->get_field_value('id'), (string) $liveform->get_field_value('recurrence_number'))) or output_error('Query failed.');
            
            // insert record for remaining spots
            $query =
                "INSERT INTO remaining_reservation_spots (
                    calendar_event_id,
                    recurrence_number,
                    number_of_remaining_spots)
                VALUES (
                    ?,
                    ?,
                    ?)";
            $result = db_q($query, array(
                (string) $liveform->get_field_value('id'),
                (string) $liveform->get_field_value('recurrence_number'),
                (string) $liveform->get_field_value('number_of_remaining_spots'))) or output_error('Query failed.');

        }

        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('Event'), $liveform->get_field_value('name') ) )), $_SESSION['sessionusername']);


        // if there is a send to, then send user there
        if ($liveform->get_field_value('send_to') != '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($liveform->get_field_value('send_to')));
            
        // else there is not a send to, so add notice and send user to calendars screen
        } else {
            $liveform_calendars = new liveform('calendars');
            $liveform->remove_form();
           
            $liveform_calendars->add_notice(lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Event') )));

            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/calendars.php');
        }
        
        
    }
}
?>