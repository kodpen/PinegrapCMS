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

include_once('liveform.class.php');
$liveform = new liveform('add_calendar_event');

// if the form was not just submitted
if (!$_POST) {
    // if the form has not been submitted yet, then set default values
    if ($liveform->field_in_session('name') == FALSE) {
        $liveform->assign_field_value('show_start_time', '1');
        $liveform->assign_field_value('show_end_time', '1');
        $liveform->assign_field_value('total_recurrence_number', '1');
        $liveform->assign_field_value('recurrence_type', 'day');
        $liveform->assign_field_value('recurrence_day_sun', '1');
        $liveform->assign_field_value('recurrence_day_mon', '1');
        $liveform->assign_field_value('recurrence_day_tue', '1');
        $liveform->assign_field_value('recurrence_day_wed', '1');
        $liveform->assign_field_value('recurrence_day_thu', '1');
        $liveform->assign_field_value('recurrence_day_fri', '1');
        $liveform->assign_field_value('recurrence_day_sat', '1');
        $liveform->assign_field_value('unpublish_days', '45');
        $liveform->assign_field_value('separate_reservations', '1');
        $liveform->assign_field_value('limit_reservations', '1');
        $liveform->assign_field_value('number_of_initial_spots', '1');
        $liveform->assign_field_value('no_remaining_spots_message', '<p>' . lang('Sorry, we are not accepting any more reservations at this time.') . '</p>');
        $liveform->assign_field_value('reserve_button_label', lang('Reserve') );
    }

    $choices = pg_calendar_event_form_choices();

    print
    pg_page_shell(
        array(
            'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Event'))),
            'extra classes'=>'calendar',
            'icon'=>'calendar', 
            'heading'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Event'))),
            'heading_description' => lang('Create a new event on one or more calendars, and publish it at any time.'),
            'cancel'=>array('enable'=>'true','url'=>'view_calendars.php'),
        
            'breadcrumb' => array(array('label' => lang('View Calendars'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/calendars.php'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Event'))))),
        )
    ) . '
<main id="content" class="container-fluid">
    ' . get_date_time_picker_format() . '
    ' . get_wysiwyg_editor_code(array('full_description', 'notes', 'no_remaining_spots_message')) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="add_calendar_event.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_calendar_event_form_cards($liveform, $user, $choices['calendars'], $choices['calendar_event_locations']) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
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
    
    $checked = pg_calendar_event_form_validate($liveform);
    $start_time = $checked['start_time'];
    $end_time = $checked['end_time'];
    $calendars = $checked['calendars'];
    $calendar_event_locations = $checked['calendar_event_locations'];
    
    // if there is an error, forward user back to add calendar event screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_calendar_event.php');
        exit();
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
    
    // If user has access to publish calendar events then allow publish fields to be updated.
    if (($user['role'] < 3) || ($user['publish_calendar_events'] == 'yes')) {

        $sql_publish_columns =
            'published,
            unpublish_days,';

        $sql_publish_values =
            "?,
            ?,";
        $publish_values = array((string) $liveform->get_field_value('published'), (string) $liveform->get_field_value('unpublish_days'));
        
    // else do not update the field
    } else {
        $sql_publish_columns = '';
        $sql_publish_values = '';
        $publish_values = array();
    }
    
    // create calendar event
    $query =
        "INSERT INTO calendar_events (
            name,
            $sql_publish_columns
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
            $sql_publish_values
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
            (string) $user['id']))) or output_error('Query failed.');
    
    $calendar_event_id = mysqli_insert_id(db::$con);
    
    // loop through all calendars, in order to create cross reference records
    foreach ($calendars as $calendar) {
        // if the calendar was selected and the user has access to the calendar
        if (($liveform->get_field_value('calendar_' . $calendar['id']) == 1) && (validate_calendar_access($calendar['id']) == true)) {
            $query =
                "INSERT INTO calendar_events_calendars_xref (
                    calendar_event_id,
                    calendar_id)
                VALUES (
                    ?,
                    ?)";
            $result = db_q($query, array((string) $calendar_event_id, (string) $calendar['id'])) or output_error('Query failed.');
        }
    }
    
    // loop through all calendar event locations, in order to create cross reference records
    foreach ($calendar_event_locations as $calendar_event_location) {
        // if the calendar event location was selected, then create cross reference record
        if ($liveform->get_field_value('calendar_event_location_' . $calendar_event_location['id']) == 1) {
            $query =
                "INSERT INTO calendar_events_calendar_event_locations_xref (
                    calendar_event_id,
                    calendar_event_location_id)
                VALUES (
                    ?,
                    ?)";
            $result = db_q($query, array((string) $calendar_event_id, (string) $calendar_event_location['id'])) or output_error('Query failed.');
        }
    }

    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('calendar event'),$liveform->get_field_value('name')))), $_SESSION['sessionusername']);
    $liveform->remove_form();
    $liveform_calendars = new liveform('calendars');
    $liveform_calendars->add_notice(lang(array('string'=>'{var:1} was created successfully','vars'=>lang('Calendar Event') )));
    
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/calendars.php');
    
    
}
?>