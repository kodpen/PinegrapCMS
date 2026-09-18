<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Calendars, events and event location availability.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function get_calendar($calendar_id, $calendars, $view, $status, $user, $date, $link, $number_of_upcoming_events = '', $return = 'html', $output_minimal_calendar = false)
{
    $sql_join = '';
    // if at least one calendar was supplied, continue
    if ($calendars) {
        // assume that calendar id has not been verified until we find out otherwise
        $calendar_id_verified = false;
        // loop through calendars in order to verify if supplied calendar is one of the supplied calendars
        foreach ($calendars as $calendar) {
            if ($calendar['id'] == $calendar_id) {
                $calendar_id_verified = true;
            }
        }
        // clear calendar id if it was not verified
        if ($calendar_id_verified == false) {
            $calendar_id = '';
        }
        // The date is MM-DD-YYYY, as the month/week navigation links build it,
        // but it also arrives from the query string and is kept in the session.
        // Anything else is dropped so the views fall back to the current date:
        // mktime() below rejects non-integer parts (a TypeError on PHP 8), and a
        // bad value that stuck in the session would break every later request.
        if ($date) {
            $date_parts = explode('-', (string) $date);
            if ((count($date_parts) != 3)
                || !ctype_digit($date_parts[0]) || !ctype_digit($date_parts[1]) || !ctype_digit($date_parts[2])
                || !checkdate((int) $date_parts[0], (int) $date_parts[1], (int) $date_parts[2])) {
                $date = '';
            }
        }
        switch ($view) {
            case '':
            case 'monthly':
                // if date is supplied, then use date
                if ($date) {
                    // Split date into parts.
                    $date_parts = explode('-', $date);
                    $start_month = $date_parts[0];
                    $start_day = '01';
                    $start_year = $date_parts[2];
                    $start_timestamp = mktime(0, 0, 0, $start_month, $start_day, $start_year);
                    $stop_month = $start_month;
                    $stop_day = date('t', $start_timestamp);
                    $stop_year = $start_year;
                    $stop_timestamp = mktime(0, 0, 0, $stop_month, $stop_day, $stop_year);
                    // else date is not supplied, so use current month
                } else {
                    $timestamp = time();
                    $start_month = date('m', $timestamp);
                    $start_day = '01';
                    $start_year = date('Y', $timestamp);
                    $start_timestamp = mktime(0, 0, 0, $start_month, $start_day, $start_year);
                    $stop_month = date('m', $timestamp);
                    $stop_day = date('t', $timestamp);
                    $stop_year = date('Y', $timestamp);
                    $stop_timestamp = mktime(0, 0, 0, $stop_month, $stop_day, $stop_year);
                }
                $decrease_timestamp = mktime(0, 0, 0, $start_month - 1, 1, $start_year);
                $decrease_date = date('m', $decrease_timestamp) . '-' . date('d', $decrease_timestamp) . '-' . date('Y', $decrease_timestamp);
                $increase_timestamp = mktime(0, 0, 0, $start_month + 1, 1, $start_year);
                $increase_date = date('m', $increase_timestamp) . '-' . date('d', $increase_timestamp) . '-' . date('Y', $increase_timestamp);
                // If we are in softwares backend, then prepare items that only appear in the backend
                if ($user) {
                    $output_date_selection = '
                    <div class="btn-group my-2">
                        <a href="?date=' . h($decrease_date) . '&amp;calendar_id=' . h($calendar_id) . '&amp;view=' . h($view) . '&amp;status=' . h($status) . '" class="btn btn-outline-primary" class="previous">&lt;</a> 
                        <a href="?date" class="btn btn-outline-primary this_month">' . lang('This Month') . '</a>
                        <a href="?date=' . h($increase_date) . '&amp;calendar_id=' . h($calendar_id) . '&amp;view=' . h($view) . '&amp;status=' . h($status) . '" class="btn btn-outline-primary" class="next">&gt;</a>
                    </div>
                    <span class="fw-bolder">' . h(lang(date('F', $start_timestamp))) . ' ' . h(date('Y', $start_timestamp)) . '</span>';
                } else {
                    // else this is for a front-end view
                    $output_date_selection = '<a href="?date=' . h($decrease_date) . '&amp;calendar_id=' . h($calendar_id) . '&amp;view=' . h($view) . '&amp;status=' . h($status) . '" class="button_3d_secondary software_button_small_secondary" class="previous">&lt;</a> 

                    <a href="?date" class="button_3d_secondary software_button_small_secondary this_month">' . lang('This Month') . '</a>

                    <a href="?date=' . h($increase_date) . '&amp;calendar_id=' . h($calendar_id) . '&amp;view=' . h($view) . '&amp;status=' . h($status) . '" class="button_3d_secondary software_button_small_secondary" class="next">&gt;</a> &nbsp; 

                    <span style="font-weight: bold; vertical-align: middle; font-size: 150%">' . h(lang(date('F', $start_timestamp))) . ' ' . h(date('Y', $start_timestamp)) . '</span>';
                }
                break;
            case 'weekly':
                // if date is supplied, then use date
                if ($date) {
                    // Split date into parts.
                    $date_parts = explode('-', $date);
                    $date_month = $date_parts[0];
                    $date_day = $date_parts[1];
                    $date_year = $date_parts[2];
                    $date_timestamp = mktime(0, 0, 0, $date_month, $date_day, $date_year);
                    // else date is not supplied, so use current week
                } else {
                    $date_timestamp = time();
                }
                // if start date is Sunday
                if (date('l', $date_timestamp) == 'Sunday') {
                    $week_start_time = strtotime('Sunday', $date_timestamp);
                } else {
                    $week_start_time = strtotime('last Sunday', $date_timestamp);
                }
                $start_month = date('m', $week_start_time);
                $start_day = date('d', $week_start_time);
                $start_year = date('Y', $week_start_time);
                $start_timestamp = mktime(0, 0, 0, $start_month, $start_day, $start_year);
                $week_stop_time = strtotime('Saturday', $week_start_time);
                $stop_month = date('m', $week_stop_time);
                $stop_day = date('d', $week_stop_time);
                $stop_year = date('Y', $week_stop_time);
                $stop_timestamp = mktime(0, 0, 0, $stop_month, $stop_day, $stop_year);
                $decrease_timestamp = strtotime('last sunday 12:00:00', $start_timestamp);
                $decrease_date = date('m', $decrease_timestamp) . '-' . date('d', $decrease_timestamp) . '-' . date('Y', $decrease_timestamp);
                $increase_timestamp = strtotime('2 Sunday', $start_timestamp);
                $increase_date = date('m', $increase_timestamp) . '-' . date('d', $increase_timestamp) . '-' . date('Y', $increase_timestamp);
                // If we are in softwares backend, then prepare items that only appear in the backend
                if ($user) {
                    $output_date_selection = '
                    <span style="font-weight: bold; vertical-align: middle;">
                        ' . get_absolute_time(array(
                            'timestamp' => $week_start_time,
                            'type' => 'date',
                            'size' => 'long'
                        )) . ' - ' . get_absolute_time(array(
                            'timestamp' => $week_stop_time,
                            'type' => 'date',
                            'size' => 'long'
                        )) . '
                    </span><br/>
                    <div class="btn-group">
                        <a href="?date=' . h($decrease_date) . '&amp;calendar_id=' . h($calendar_id) . '&amp;view=' . h($view) . '&amp;status=' . h($status) . '" class="btn btn-outline-primary">&lt;</a> 
                        <a href="?date" class="btn btn-outline-primary this_week">' . lang('This Week') . '</a>
                        <a href="?date=' . h($increase_date) . '&amp;calendar_id=' . h($calendar_id) . '&amp;view=' . h($view) . '&amp;status=' . h($status) . '" class="btn btn-outline-primary">&gt;</a> 
                    </div> ';
                } else {
                    // else this is for a front-end view
                    $output_date_selection = '<a href="?date=' . h($decrease_date) . '&amp;calendar_id=' . h($calendar_id) . '&amp;view=' . h($view) . '&amp;status=' . h($status) . '" class="software_button_small_secondary">&lt;</a> <span style="font-weight: bold; vertical-align: middle;">' . get_absolute_time(array(
                        'timestamp' => $week_start_time,
                        'type' => 'date',
                        'size' => 'long'
                    )) . ' - ' . get_absolute_time(array(
                            'timestamp' => $week_stop_time,
                            'type' => 'date',
                            'size' => 'long'
                        )) . '</span> <a href="?date=' . h($increase_date) . '&amp;calendar_id=' . h($calendar_id) . '&amp;view=' . h($view) . '&amp;status=' . h($status) . '" class="software_button_small_secondary">&gt;</a> <a href="?date" class="software_button_small_secondary this_week">' . lang('This Week') . '</a>';
                }


                break;
            case 'upcoming':
                // set current timestamp
                $date_timestamp = time();
                // set start date
                $start_month = date('m', $date_timestamp);
                $start_day = date('d', $date_timestamp);
                $start_year = date('Y', $date_timestamp);
                // set end date
                $stop_month = '99';
                $stop_day = '99';
                $stop_year = '9999';
                break;
        }
        $start_date = $start_month . '-' . $start_day . '-' . $start_year;
        $start_date_for_comparison = $start_year . '-' . $start_month . '-' . $start_day;
        $stop_date = $stop_month . '-' . $stop_day . '-' . $stop_year;
        $stop_date_for_comparison = $stop_year . '-' . $stop_month . '-' . $stop_day;
        $output_calendar_pick_list = '';
        $output_update_calendar_view_form = '';
        // if the view is monthly, weekly, or if we are in the backend, then prepare calendar update form
        if (($view == 'monthly') || ($view == 'weekly') || ($user)) {
            // if there is more than one calendar, then prepare to output calendar pick list
            if (count($calendars) > 1) {
                $output_calendar_options = '<option value="">-' . lang('All Calendars') . '-</option>';
                // loop through all calendars in order to prepare calendar pick list
                foreach ($calendars as $calendar) {
                    // if calendar is the current selected calendar, prepare to select calendar
                    if ($calendar['id'] == $calendar_id) {
                        $selected = ' selected="selected"';
                    } else {
                        $selected = '';
                    }
                    // prepare to output calendar option
                    $output_calendar_options .= '<option value="' . h($calendar['id']) . '"' . $selected . '>' . h($calendar['name']) . '</option>';
                }
                // if this is for a back-end view then set the update button class
                if ($user) {
                    $output_calendar_pick_list = '<select name="calendar_id" class="form-select my-1" onchange="submit_form(\'form\')">' . $output_calendar_options . '</select>';
                } else {
                    // else this is for a front-end view
                    $output_calendar_pick_list = '<select name="calendar_id" class="software_select">' . $output_calendar_options . '</select>';
                }

            }

            $output_view_monthly_selected = '';
            $output_view_weekly_selected = '';
            switch ($view) {
                case 'monthly':
                    $output_view_monthly_selected = ' selected="selected"';
                    break;
                case 'weekly':
                    $output_view_weekly_selected = ' selected="selected"';
                    break;
            }

            $output_status_pick_list = '';

            // if this is for a back-end view then set the update button class
            if ($user) {

                $all_selected = '';
                $published_selected = '';
                $not_published_selected = '';
                switch ($status) {
                    case '':
                        $all_selected = ' selected="selected"';
                        break;
                    case 'published':
                        $published_selected = ' selected="selected"';
                        break;
                    case 'not_published':
                        $not_published_selected = ' selected="selected"';
                        break;
                }

                $output_update_calendar_view_form = '
                    <div class="card-header">
                        <div class="calendar_view row my-2">
                            <div class="col-12 col-md-12 col-lg-6 text-center text-lg-start">
                                ' . $output_date_selection . '
                            </div>
                            <div class="col-12 col-md-12 col-lg-6 text-center text-lg-start">
                                <form id="form" method="get" class="row justify-content-end">
                                    <input type="hidden" name="date" value="' . h($date) . '">
                                    <div class="col-12 col-md-4 col-lg-3 align-self-center">
                                        ' . $output_calendar_pick_list . '
                                    </div>
                                    <div class="col-12 col-md-4 col-lg-3 align-self-center">
                                        <select name="view" class="form-select my-1" onchange="submit_form(\'form\')">
                                            <option value="monthly"' . $output_view_monthly_selected . '>' . lang('Monthly') . '</option>
                                            <option value="weekly"' . $output_view_weekly_selected . '>' . lang('Weekly') . '</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4 col-lg-6 align-self-center">
                                        <div class="input-group">
                                            <div class="input-group-text my-1">' . lang('Status') . ':</div>
                                            <select name="status" class="form-select my-1" onchange="submit_form(\'form\')"><option value=""' . $all_selected . '>-' . lang('All') . '-</option><option value="published"' . $published_selected . '>' . lang('Published') . '</option><option value="not_published"' . $not_published_selected . '>*' . lang('Not Published') . '</option></select>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';


            } else {
                // else this is for a front-end view

                $output_update_calendar_view_form = '<div class="calendar_view">

                    <div style="float: left; padding-right: 1em; padding-bottom: .5em; margin-top: .25em;">' . $output_date_selection . '</div>

                    <div class="desktop_right" style="float: right">

                        <form method="get">

                            <input type="hidden" name="date" value="' . h($date) . '">

                            <table>

                                <tr>

                                    <td class="mobile_left mobile_width" style="padding-right: 1em; padding-bottom: .5em;">

                                        ' . $output_calendar_pick_list . '

                                    </td>

                                    <td class="mobile_left mobile_width" style="padding-right: 1em; padding-bottom: .5em;">

                                        <select name="view" class="software_select">

                                            <option value="monthly"' . $output_view_monthly_selected . '>' . lang('Monthly') . '</option>

                                            <option value="weekly"' . $output_view_weekly_selected . '>' . lang('Weekly') . '</option>

                                        </select>

                                    </td>

                                        ' . $output_status_pick_list . '

                                    <td class="mobile_left mobile_width" style="padding-right: 0; padding-bottom: .5em;">
                                        <button type="submit" name="submit_update" value="Update" class="software_input_submit_small_secondary">' . lang('Update') . '</button>
                                    </td>

                                </tr>

                            </table>

                        </form>

                    </div>

                    <div style="clear: both"></div>

                </div>';
            }
        }
        // prepare sql for status
        $sql_status = "";
        switch ($status) {
            case 'published':
                $sql_status = "(calendar_events.published = '1')";
                break;
            case 'not_published':
                $sql_status = "(calendar_events.published = '0')";
                break;
        }
        $sql_calendar = "";
        // if a calendar was supplied, prepare SQL to only get calendar events for that calendar
        if ($calendar_id) {
            // if there is sql for the status, then prepare AND
            if ($sql_status != '') {
                $sql_calendar .= "AND ";
            }
            $sql_join = "LEFT JOIN calendar_events_calendars_xref ON calendar_events.id = calendar_events_calendars_xref.calendar_event_id";
            $sql_calendar .= "(calendar_events_calendars_xref.calendar_id = '" . escape($calendar_id) . "')";
            // else a calendar was not supplied, so prepare SQL to only get calendars that were supplied
        } else {
            // If this is not the back end
            if (!$user) {
                // if there is sql for the status, then prepare AND
                if ($sql_status != '') {
                    $sql_calendar .= "AND ";
                }
                $sql_calendars = "";
                // For each calendar, create an or sql statement
                foreach ($calendars as $calendar) {
                    if ($sql_calendars) {
                        $sql_calendars .= " OR ";
                    }
                    $sql_calendars .= "(calendar_events_calendars_xref.calendar_id = '" . escape($calendar['id']) . "')";
                }
                $sql_calendar .= "($sql_calendars)";
                $sql_join = "LEFT JOIN calendar_events_calendars_xref ON calendar_events.id = calendar_events_calendars_xref.calendar_event_id";
            }
        }
        $sql_where = "";
        if (($sql_status != '') || ($sql_calendar != '')) {
            $sql_where = "WHERE

                    $sql_status

                    $sql_calendar";
        }
        // get calendar event exceptions
        $query = "SELECT

                calendar_event_id,

                recurrence_number

            FROM calendar_event_exceptions";
        $result = mysqli_query(db::$con, $query);
        $calendar_event_exceptions = array();
        // Place all of the exceptions into an array
        while ($row = mysqli_fetch_assoc($result)) {
            $calendar_event_exceptions[] = $row;
        }
        // get calendar events
        $query = "SELECT

                calendar_events.id,

                calendar_events.name,

                calendar_events.published,

                calendar_events.short_description,

                calendar_events.all_day,

                calendar_events.start_time,

                calendar_events.recurrence_number,

                calendar_events.recurrence_type,

                calendar_events.recurrence_day_sun,

                calendar_events.recurrence_day_mon,

                calendar_events.recurrence_day_tue,

                calendar_events.recurrence_day_wed,

                calendar_events.recurrence_day_thu,

                calendar_events.recurrence_day_fri,

                calendar_events.recurrence_day_sat,

                calendar_events.recurrence_month_type,

                calendar_events.reservations

            FROM calendar_events

            $sql_join

            $sql_where";
        $result = mysqli_query(db::$con, $query);
        $calendar_events = array();
        // Add each event information to an array
        while ($row = mysqli_fetch_assoc($result)) {
            $calendar_events[] = $row;
        }
        $events = array();
        $processed_events = array();
        // loop through all calendar events
        foreach ($calendar_events as $calendar_event) {
            $id = $calendar_event['id'];
            $name = $calendar_event['name'];
            $published = $calendar_event['published'];
            $short_description = $calendar_event['short_description'];
            $all_day = $calendar_event['all_day'];
            $event_start_date_and_time = $calendar_event['start_time'];
            $recurrence_number = $calendar_event['recurrence_number'];
            $recurrence_type = $calendar_event['recurrence_type'];
            $recurrence_day_sun = $calendar_event['recurrence_day_sun'];
            $recurrence_day_mon = $calendar_event['recurrence_day_mon'];
            $recurrence_day_tue = $calendar_event['recurrence_day_tue'];
            $recurrence_day_wed = $calendar_event['recurrence_day_wed'];
            $recurrence_day_thu = $calendar_event['recurrence_day_thu'];
            $recurrence_day_fri = $calendar_event['recurrence_day_fri'];
            $recurrence_day_sat = $calendar_event['recurrence_day_sat'];
            $recurrence_month_type = $calendar_event['recurrence_month_type'];
            $reservations = $calendar_event['reservations'];
            // If the event id has not already been processed, process it and log that it has been processed.
            if (!in_array($id, $processed_events)) {
                // Keep track that we have already processed this event.
                $processed_events[] = $id;
                // if a user was not supplied or user has access to calendar event, then continue
                if (!$user || (validate_calendar_event_access($id) == true)) {
                    // split event start date and time into parts
                    $event_start_date_and_time_parts = explode(' ', $event_start_date_and_time);
                    $event_start_date = $event_start_date_and_time_parts[0];
                    $event_start_time = $event_start_date_and_time_parts[1];
                    $calendar_exceptions = array();
                    // if recurrence number is greater than zero, then split event start date into parts, that we will use later
                    if ($recurrence_number > 0) {
                        $event_start_date_parts = explode('-', $event_start_date);
                        $event_start_year = $event_start_date_parts[0];
                        $event_start_month = $event_start_date_parts[1];
                        $event_start_day = $event_start_date_parts[2];
                        // Loop through all calendar exceptions and separate the ones we need for the event we are working with into $calendar_exceptions
                        foreach ($calendar_event_exceptions as $calendar_event_exception) {
                            if ($calendar_event_exception['calendar_event_id'] == $id) {
                                $calendar_exceptions[] = $calendar_event_exception['recurrence_number'];
                            }
                        }
                        // If this is a monthly event and the month type is "day of the week",
                        // then determine which week in the month the event is on.
                        // If the week is 1-4 then we will use that, however if the week is 5,
                        // then we interpret that as the last week.
                        if (($recurrence_type == 'month') && ($recurrence_month_type == 'day_of_the_week')) {
                            $day_of_the_week = date('l', strtotime($event_start_date));
                            $first_day_of_the_month_timestamp = strtotime($event_start_year . '-' . $event_start_month . '-01');
                            $week = '';
                            // Create a loop in order to determine which week event falls on.
                            // We only loop through 4 weeks, because we are going to set "last" below for 5th week.
                            for ($week_index = 0; $week_index <= 3; $week_index++) {
                                // If the event is in this week, then remember the week number and break out of this loop.
                                if ($event_start_date == date('Y-m-d', strtotime('+' . $week_index . ' week ' . $day_of_the_week, $first_day_of_the_month_timestamp))) {
                                    $week = $week_index + 1;
                                    break;
                                }
                            }
                            // If a week was not found, then that means it falls on the 5th week,
                            // so set it to be the last week.
                            if ($week == '') {
                                $week = 'last';
                            }
                        }
                    }
                    // loop in order to create a new event for each recurrence
                    for ($i = 0; $i <= $recurrence_number; $i++) {
                        // We will use this variable to stop the calculations if the recurrence number is hidden by an exception
                        $halt_on_exception = '0';
                        $hidden_exception = '0';
                        // If calendar_exceptions is not empty.
                        if (count($calendar_exceptions) > 0) {
                            // Check if this recurrence is an exception.
                            if (in_array($i, $calendar_exceptions)) {
                                // If we are not in software's backend, do not worry about this date anymore.
                                if (!$user) {
                                    $halt_on_exception = '1';
                                    // else, we are in softwares backend, so print the correct link (remove, unremove) to all the user to make an exception
                                } else {
                                    $halt_on_exception = '0';
                                    $hidden_exception = '1';
                                }
                                // The recurrence was not an exception, so do not hide it.
                            } else {
                                $hidden_exception = '0';
                            }
                        }
                        // if recurrence number is greater than 0, then adjust event start date
                        if ($i > 0) {
                            // adjust event start date depending on recurrence type
                            switch ($recurrence_type) {
                                // Daily
                                case 'day':
                                    $count = 0;
                                    // Loop through days in the future until we find a date that is valid
                                    // based on the valid days of the week that were selected.
                                    while (true) {
                                        $new_time = strtotime('+1 day', strtotime($event_start_date));
                                        $event_start_date = date('Y-m-d', $new_time);
                                        $day_of_the_week = strtolower(date('D', $new_time));
                                        // If this day of the week is valid for this calendar event,
                                        // then we have found a valid date, so break out of the loop.
                                        if (${'recurrence_day_' . $day_of_the_week} == 1) {
                                            break;
                                        }
                                        $count++;
                                        // If we have already looped 7 times, then something is wrong,
                                        // so break out of this loop and the recurrence loop above.
                                        // This should never happen but is added just in case in order to
                                        // prevent an endless loop.
                                        if ($count == 7) {
                                            break 3;
                                        }
                                    }
                                    break;
                                // Weekly
                                case 'week':
                                    $new_time = mktime(0, 0, 0, $event_start_month, $event_start_day + (7 * $i), $event_start_year);
                                    $event_start_date = date('Y', $new_time) . '-' . date('m', $new_time) . '-' . date('d', $new_time);
                                    break;
                                // Monthly
                                case 'month':
                                    switch ($recurrence_month_type) {
                                        case 'day_of_the_month':
                                            $new_time = mktime(0, 0, 0, $event_start_month + $i, 1, $event_start_year);
                                            $new_event_start_year = date('Y', $new_time);
                                            $new_event_start_month = date('m', $new_time);
                                            $new_event_start_day = $event_start_day;
                                            // if date is not valid, then get last date for month
                                            if (checkdate($new_event_start_month, $new_event_start_day, $new_event_start_year) == false) {
                                                $new_event_start_day = date('t', mktime(0, 0, 0, $new_event_start_month, 1, $new_event_start_year));
                                            }
                                            $event_start_date = $new_event_start_year . '-' . $new_event_start_month . '-' . $new_event_start_day;
                                            break;
                                        case 'day_of_the_week':
                                            $first_day_of_the_month_timestamp = mktime(0, 0, 0, $event_start_month + $i, 1, $event_start_year);
                                            // If the week is 1-4 then find the date in a certain way.
                                            if ($week != 'last') {
                                                $week_index = $week - 1;
                                                $new_time = strtotime('+' . $week_index . ' week ' . $day_of_the_week, $first_day_of_the_month_timestamp);
                                                // Otherwise the week is last, so find the date in a different way.
                                            } else {
                                                $last_day_of_the_month_timestamp = strtotime(date('Y-m-t', $first_day_of_the_month_timestamp));
                                                // If the last day of the month happens to be the right day of the week,
                                                // then thats that day that we want.
                                                if (date('l', $last_day_of_the_month_timestamp) == $day_of_the_week) {
                                                    $new_time = $last_day_of_the_month_timestamp;
                                                    // Otherwise find the day of the week that we want in the last week of the month.
                                                } else {
                                                    $new_time = strtotime('last ' . $day_of_the_week, $last_day_of_the_month_timestamp);
                                                }
                                            }
                                            $event_start_date = date('Y-m-d', $new_time);
                                            break;
                                    }
                                    break;
                                // Yearly
                                case 'year':
                                    $new_event_start_year = $event_start_year + $i;
                                    $new_event_start_month = $event_start_month;
                                    $new_event_start_day = $event_start_day;
                                    // if date is not valid, then get last date for month
                                    if (checkdate($new_event_start_month, $new_event_start_day, $new_event_start_year) == false) {
                                        $new_event_start_day = date('t', mktime(0, 0, 0, $new_event_start_month, 1, $new_event_start_year));
                                    }
                                    $event_start_date = $new_event_start_year . '-' . $new_event_start_month . '-' . $new_event_start_day;
                                    break;
                            }
                        }
                        if ($halt_on_exception == '0') {
                            // if the event start date is in the selected date range, add event to array
                            if (($start_date_for_comparison <= $event_start_date) && ($event_start_date <= $stop_date_for_comparison)) {
                                $events[] = array(
                                    'id' => $id,
                                    'name' => $name,
                                    'published' => $published,
                                    'short_description' => $short_description,
                                    'event_start_date' => $event_start_date,
                                    'event_start_time' => $event_start_time,
                                    'event_start_date_and_time' => $event_start_date . ' ' . $event_start_time,
                                    'recurring_event' => $recurrence_number,
                                    'recurrence_number' => $i,
                                    'all_day' => $all_day,
                                    'hidden_exception' => $hidden_exception,
                                    'reservations' => $reservations
                                );
                            }
                        }
                        // if the event start date is greater than or equal to stop date, then break out of loop,
                        // because it is not possible for further recurrences to be in date range
                        if ($event_start_date >= $stop_date_for_comparison) {
                            break;
                        }
                    }
                }
            }
        }
        // create array for storing event start dates and times, so that we can sort events later
        $event_start_dates_and_times = array();
        // If this is for the front-end, then create array to store the calendars that calendar events are assigned to.
        // We store this data in an array, so that we don't have to do duplicate
        // database queries for multiple recurrences of the same event.
        if (!$user) {
            $calendar_events_calendars = array();
        }
        // Loop through events in order to populate start date and times array
        // and to get calendars that each event is assigned to.
        foreach ($events as $key => $event) {
            $event_start_dates_and_times[$key] = $event['event_start_date_and_time'];
            // If this is for the front-end, then prepare to get calendars for this event
            // so we can output a class for each calendar (e.g. in order to add a different
            // background color behind each event based on the calendar that the event is in).
            if (!$user) {
                // If this event has not already been processed, then get calendars that this event is assigned to.
                if (isset($calendar_events_calendars[$event['id']]) == false) {
                    $query = "SELECT calendar_id FROM calendar_events_calendars_xref WHERE calendar_event_id = '" . $event['id'] . "'";
                    $result = mysqli_query(db::$con, $query);
                    $calendar_events_calendars[$event['id']] = array();
                    while ($row = mysqli_fetch_assoc($result)) {
                        $calendar_events_calendars[$event['id']][] = $row['calendar_id'];
                    }
                }
                // Store list of calendars for this event.
                $events[$key]['calendars'] = $calendar_events_calendars[$event['id']];
            }
        }
        // sort events by date and time
        array_multisort($event_start_dates_and_times, SORT_ASC, $events);
        // Output Calendar based on View
        if ($view == 'upcoming') {
            // If the request asked for the data as an array instead of HTML, then just return that.
            if ($return == 'array') {
                if ($number_of_upcoming_events) {
                    return array_slice($events, 0, $number_of_upcoming_events);
                } else {
                    return $events;
                }
            }
            // Output Upcoming View
            // if there is at least one event, prepare to list events
            if ($events) {
                // If the date format is month and then day, then use that format.
                if (DATE_FORMAT == 'month_day') {
                    $output_day_and_month = h(lang(date('F', strtotime($event['event_start_date']))) . ' ' . date('j', strtotime($event['event_start_date'])));
                    // Otherwise the date format is day and then month, so use that format.
                } else {
                    $output_day_and_month = h(date('j', strtotime($event['event_start_date'])) . ' ' . lang(date('F', strtotime($event['event_start_date']))));
                }
                $output_calendar_data = '';
                $event_counter = 0;
                // loop through all events in order to build list
                foreach ($events as $key => $event) {
                    // increment the event counter
                    $event_counter++;
                    // if event start date is not equal to previous event start date, then open new container for this day
                    if ($event['event_start_date'] != $events[$key - 1]['event_start_date']) {
                        $output_calendar_data .= '<p class="row_' . ($event_counter % 2) . '"><span style="font-weight:bold;">' . h(lang(date('l', strtotime($event['event_start_date'])))) . ', ' . $output_day_and_month . '</span><br>';
                    }
                    $output_calendar_classes = '';
                    // Loop through the calendars for this event in order to add a class for calendar that the event is assigned to.
                    // (e.g. in order to add a different background color behind each event based on the calendar that the event is in).
                    foreach ($event['calendars'] as $calendar) {
                        // If a class has already been added, then add a space for separation.
                        if ($output_calendar_classes != '') {
                            $output_calendar_classes .= ' ';
                        }
                        $output_calendar_classes .= 'calendar_' . $calendar;
                    }
                    $recurring_query_string = '';
                    // If the event is recurring.
                    if ($event['recurrence_number']) {
                        $recurring_query_string = '&recurrence_number=' . $event['recurrence_number'];
                    }
                    // if there should be a link, then prepare link
                    if ($link) {
                        // get additional calendar event data
                        $calendar_event = get_calendar_event($event['id'], $event['recurrence_number']);
                        $output_availability_icon = '';
                        // if this event is published
                        // and it does not have an exception
                        // and reservations are enabled
                        // and the reservation product still exists
                        // and the reservation product is enabled
                        // then determine which availability icon we should show
                        if (($event['published'] == 1) && ($event['hidden_exception'] == 0) && ($event['reservations'] == 1) && ($calendar_event['product_id'] != '') && ($calendar_event['product_enabled'] == 1)) {
                            // if the event is not in the past
                            // and reservations are not limited
                            // or the number of remaining spots is greater than 0
                            // and inventory is disabled for product
                            // or the inventory quantity is greater than 0
                            // or the product is allowed to be backordered
                            // then this calendar event is available, so output available icon
                            if ((strtotime($calendar_event['end_date_and_time']) >= time()) && (($calendar_event['limit_reservations'] == 0) || ($calendar_event['number_of_remaining_spots'] > 0)) && (($calendar_event['inventory'] == 0) || ($calendar_event['inventory_quantity'] > 0) || ($calendar_event['backorder'] == 1))) {
                                $output_availability_icon = '<span style="color: green; font-weight: bold; font-size: 130%; margin-right: .25em">&#9679;</span>';
                                // else this calendar event is not available, so output unavailable icon
                            } else {
                                $output_availability_icon = '<span style="color: lightgrey; font-weight: bold; font-size: 130%; margin-right: .25em">&#9679;</span>';
                            }
                        }
                        $output_link = $output_availability_icon . '<a href="' . h(escape_url($link)) . '?id=' . h($event['id']) . h($recurring_query_string) . '">' . h($event['name']) . '</a>';
                        // else there should not be a link, so prepare plain text
                    } else {
                        $output_link = h($event['name']);
                    }
                    // output event on it's own line
                    $output_calendar_data .= '<span class="' . $output_calendar_classes . '">' . $output_link . '</span><br />';
                    // if next event start date is not equal to current event start date, or if this is the last event to be outputted, then close container for this day
                    if (($events[$key + 1]['event_start_date'] != $event['event_start_date']) || ($event_counter == $number_of_upcoming_events)) {
                        $output_calendar_data .= '</p>';
                    }
                    // if the number of upcoming events is greater than zero, and if the event counter is equal to the number of upcoming events
                    // then break loop.
                    if (($number_of_upcoming_events > 0) && ($event_counter == $number_of_upcoming_events)) {
                        break;
                    }
                }
                // else there are no events, so prepare notice
            } else {
                $output_calendar_data = '<div>' . lang('There are no upcoming calendar events.') . '</div>';
            }
            $output_calendar = '<div class="upcoming">' . $output_calendar_data . '</div>';
        } else {
            // Output Monthly View
            if ($view != 'weekly') {
                $days = array();
                // loop through all events in order to add events to days array
                foreach ($events as $key => $event) {
                    // split event start date into parts in order to get day of the month
                    $event_start_date_parts = explode('-', $event['event_start_date']);
                    $event_start_day = $event_start_date_parts[2];
                    // remove 0 before day number
                    settype($event_start_day, "integer");
                    // add event to days array
                    $days[$event_start_day][] = $event;
                }
                $output_calendar_data = '';
                $start_day_of_week = date('w', $start_timestamp);
                $stop_day_of_week = date('w', $stop_timestamp);
                $output_calendar_data .= '<tr class="data">';

                // loop through all days from month before that appear greyed-out on calendar
                for ($i = 1; $i <= $start_day_of_week; $i++) {
                    $output_calendar_data .= '<td class="inactive">&nbsp;</td>';
                }
                // loop through all days in the month
                for ($i = 1; $i <= $stop_day; $i++) {
                    $day_timestamp = mktime(0, 0, 0, $start_month, $i, $start_year);
                    $current_day_timestamp = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
                    $day_of_week = date('l', $day_timestamp);
                    // if this day is a Sunday and this is not the first day, then start new row
                    if (($day_of_week == 'Sunday') && ($i != 1)) {
                        $output_calendar_data .= '<tr class="data">';
                    }
                    if ($day_timestamp == $current_day_timestamp) {
                        $output_day_number = '<span class="today">' . $i . '</span>';
                        $output_today_class = 'today';
                    } else {
                        $output_day_number = $i;
                        $output_today_class = '';
                    }
                    // start cell
                    $output_calendar_data .= '<td class="' . $output_today_class . '"><div style="text-align: right;" class="calendar-day-wrapper">' . $output_day_number . '</div>';
                    // if there are events for this day
                    if (isset($days[$i]) && $days[$i]) {

                        $output_calendar_data .= '<span class="calendar-events">';

                        // loop through all events for this day
                        foreach ($days[$i] as $event) {
                            // Initialize variables
                            $start_strikethrough_tag = '';
                            $end_strikethrough_tag = '';
                            $exception_link = '';
                            $recurring_query_string = '';
                            $asterisk = '';
                            $link_access = true;
                            $output_calendar_classes = '';
                            // If this is for the front-end, then output a class for each calendar that the event is in
                            // (e.g. in order to add a different background color behind each event based on the calendar that the event is in).
                            if (!$user) {
                                // Loop through the calendars for this event in order to prepare classes.
                                foreach ($event['calendars'] as $calendar) {
                                    // If a class has already been added, then add a space for separation.
                                    if ($output_calendar_classes != '') {
                                        $output_calendar_classes .= ' ';
                                    }
                                    $output_calendar_classes .= 'calendar_' . $calendar;
                                }
                            }
                            // get additional calendar event data
                            $calendar_event = get_calendar_event($event['id'], $event['recurrence_number']);
                            // If the event is recurring.
                            if ($event['recurrence_number']) {
                                $recurring_query_string = '&recurrence_number=' . $event['recurrence_number'];
                            }
                            // If we are in softwares backend, then prepare items that only appear in the backend
                            if ($user) {
                                // If this event is an exception, then prepare to output strikethrough tag and remove exception link
                                if ($event['hidden_exception'] == '1') {
                                    $start_strikethrough_tag = '<span class="calendar_event_exception">';
                                    $end_strikethrough_tag = '</span>';
                                    // If the calendar event is not published, or the user is greater than a basic user, or the user has publish rights, then prepare to output exception link
                                    if (($event['published'] == 0) || ($user['role'] < 3) || ($user['publish_calendar_events'] == true)) {
                                        $exception_link = '<a href="update_calendar_event_exception.php?action=delete&amp;calendar_event_id=' . h($event['id']) . '&amp;recurrence_number=' . h($event['recurrence_number']) . get_token_query_string_field() . '">' . lang('Unremove') . '</a>';
                                    }
                                    // Else this event is not an exception, so if it is a recurring event, then prepare to output add exception link
                                } else if ($event['recurring_event'] > 0) {
                                    // If the calendar event is not published, or the user is greater than a basic user, or the user has publish rights, then prepare to output exception link
                                    if (($event['published'] == 0) || ($user['role'] < 3) || ($user['publish_calendar_events'] == true)) {
                                        $exception_link = '<a href="update_calendar_event_exception.php?action=create&amp;calendar_event_id=' . h($event['id']) . '&amp;recurrence_number=' . h($event['recurrence_number']) . get_token_query_string_field() . '">' . lang('Remove') . '</a>';
                                    }
                                }
                                // if event is not published, then prepare asterisk
                                if ($event['published'] == 0) {
                                    $asterisk = '*';
                                    // Else the event is published, so check to make sure that user has access to edit calendar event
                                } else {
                                    // if user is a basic user, and does not have rights to publish calendar events then the user should not have access to the link
                                    if (($user['role'] == 3) && ($user['publish_calendar_events'] == false)) {
                                        $link_access = false;
                                    }
                                }
                            }
                            $output_availability_icon = '';
                            // if this event is published
                            // and it does not have an exception
                            // and reservations are enabled
                            // and the reservation product still exists
                            // and the reservation product is enabled
                            // then determine which availability icon we should show
                            if (($event['published'] == 1) && ($event['hidden_exception'] == 0) && ($event['reservations'] == 1) && ($calendar_event['product_id'] != '') && ($calendar_event['product_enabled'] == 1)) {
                                // if the event is not in the past
                                // and reservations are not limited
                                // or the number of remaining spots is greater than 0
                                // and inventory is disabled for product
                                // or the inventory quantity is greater than 0
                                // or the product is allowed to be backordered
                                // then this calendar event is available, so output available icon
                                if ((strtotime($calendar_event['end_date_and_time']) >= time()) && (($calendar_event['limit_reservations'] == 0) || ($calendar_event['number_of_remaining_spots'] > 0)) && (($calendar_event['inventory'] == 0) || ($calendar_event['inventory_quantity'] > 0) || ($calendar_event['backorder'] == 1))) {
                                    $output_availability_icon = '<span style="color: green; font-weight: bold; font-size: 130%; margin-right: .25em">&#9679;</span>';
                                    // else this calendar event is not available, so output unavailable icon
                                } else {
                                    $output_availability_icon = '<span style="color: lightgrey; font-weight: bold; font-size: 130%; margin-right: .25em">&#9679;</span>';
                                }
                            }
                            // If there is a page to link and the user has access to the link, then prepare to output the event name with a link
                            if ($link && ($link_access == true)) {
                                $output_link = '<a href="' . h(escape_url($link)) . '?id=' . h($event['id']) . h($recurring_query_string) . '">' . $output_availability_icon . '<strong>' . h($event['name']) . '</strong></a><br>';
                                // Else there should not be a link, so prepare to just output the event name
                            } else {
                                $output_link = $output_availability_icon . '<strong>' . h($event['name']) . '</strong><br>';
                            }
                            $output_time = '';
                            // If there is a time range, then output it.
                            if ($calendar_event['time_range'] != '') {
                                $output_time = $calendar_event['time_range'] . '<br>';
                            }

                            if ($output_minimal_calendar == true) {
                                $output_calendar_data .= '<span class="calendar-event-icon">*</span>';
                            } else {
                                $output_calendar_data .= '<div class="' . $output_calendar_classes . ' calendar-event" style="margin-bottom: .8em">
                                        ' . $start_strikethrough_tag . '
                                        ' . $asterisk . $output_link . '
                                        ' . $output_time . '
                                        ' . $end_strikethrough_tag . '
                                        ' . $exception_link . '
                                    </div>';
                            }
                        }
                        $output_calendar_data .= '</span>';
                    }
                    // end cell
                    $output_calendar_data .= '</td>';
                    // if this day is a Saturday and this is not the last day, then close row
                    if (($day_of_week == 'Saturday') && ($i != $stop_day)) {
                        $output_calendar_data .= '</tr>';
                    }
                }
                // loop through all days from month after that appear greyed-out on calendar
                for ($i = $stop_day_of_week + 1; $i <= 6; $i++) {
                    $output_calendar_data .= '<td class="inactive">&nbsp;</td>';
                }
                $output_calendar_data .= '</tr>';
                // if this is for a back-end view
                if ($user) {
                    $output_monthly_calendar_class = ' class="monthly_calendar"';
                    // else this is for a front-end view
                } else {
                    $output_monthly_calendar_class = ' class="software_monthly_calendar"';
                }
                $output_calendar = '<div class="monthly">

                        <table' . $output_monthly_calendar_class . '>

                        <tr class="heading">

                            <th>' . lang('Sunday') . '</th>

                            <th>' . lang('Monday') . '</th>

                            <th>' . lang('Tuesday') . '</th>

                            <th>' . lang('Wednesday') . '</th>

                            <th>' . lang('Thursday') . '</th>

                            <th>' . lang('Friday') . '</th>

                            <th>' . lang('Saturday') . '</th>

                        </tr>

                        ' . $output_calendar_data . '

                        </table>

                    </div>';
            }
            // Output Weekly View (including Monthly View for responsive designs)
            // if there is at least one event, prepare to list events
            if ($events) {
                $output_fieldset_class = '';
                // if this is for a front-end view
                if (!$user) {
                    $output_fieldset_class = ' class="software_fieldset"';
                }
                $output_legend_class = '';
                // if this is for a front-end view
                if (!$user) {
                    $output_legend_class = ' class="software_legend"';
                }
                $output_calendar_data = '';
                // loop through all events in order to build list
                foreach ($events as $key => $event) {
                    // if event start date is not equal to previous event start date, then open new container for this day
                    if ($event['event_start_date'] != $events[$key - 1]['event_start_date']) {
                        $output_calendar_data .= '<fieldset style="margin-bottom: 15px"' . $output_fieldset_class . '>

                                    <legend' . $output_legend_class . '>' . get_absolute_time(array(
                                'timestamp' => strtotime($event['event_start_date']),
                                'type' => 'date',
                                'size' => 'long'
                            )) . '</legend>

                                    <div style="margin: 10px">';
                    }
                    // Initialize variables
                    $start_strikethrough_tag = '';
                    $end_strikethrough_tag = '';
                    $exception_link = '';
                    $recurring_query_string = '';
                    $asterisk = '';
                    $link_access = true;
                    $output_calendar_classes = '';
                    // If this is for the front-end, then output a class for each calendar that the event is in
                    // (e.g. in order to add a different background color behind each event based on the calendar that the event is in).
                    if (!$user) {
                        // Loop through the calendars for this event in order to prepare classes.
                        foreach ($event['calendars'] as $calendar) {
                            // If a class has already been added, then add a space for separation.
                            if ($output_calendar_classes != '') {
                                $output_calendar_classes .= ' ';
                            }
                            $output_calendar_classes .= 'calendar_' . $calendar;
                        }
                    }
                    // get additional calendar event data
                    $calendar_event = get_calendar_event($event['id'], $event['recurrence_number']);
                    // If we are in softwares backend, then prepare items that only appear in the backend
                    if ($user) {
                        // If this event is an exception, then prepare to output strikethrough tag and remove exception link
                        if ($event['hidden_exception'] == '1') {
                            $start_strikethrough_tag = '<span class="calendar_event_exception">';
                            $end_strikethrough_tag = '</span>';
                            // If the calendar event is not published, or the user is greater than a basic user, or the user has publish rights, then prepare to output exception link
                            if (($event['published'] == 0) || ($user['role'] < 3) || ($user['publish_calendar_events'] == true)) {
                                $exception_link = '<a href="update_calendar_event_exception.php?action=delete&amp;calendar_event_id=' . h($event['id']) . '&amp;recurrence_number=' . h($event['recurrence_number']) . get_token_query_string_field() . '">' . lang('Unremove') . '</a><br>';
                            }
                            // Else this event is not an exception, so if it is a recurring event, then prepare to output add exception link
                        } else if ($event['recurring_event'] > 0) {
                            // If the calendar event is not published, or the user is greater than a basic user, or the user has publish rights, then prepare to output exception link
                            if (($event['published'] == 0) || ($user['role'] < 3) || ($user['publish_calendar_events'] == true)) {
                                $exception_link = '<a href="update_calendar_event_exception.php?action=create&amp;calendar_event_id=' . h($event['id']) . '&amp;recurrence_number=' . h($event['recurrence_number']) . get_token_query_string_field() . '">' . lang('Remove') . '</a><br>';
                            }
                        }
                        // if event is not published, then prepare asterisk
                        if ($event['published'] == 0) {
                            $asterisk = '*';
                            // Else the event is published, so check to make sure that user has access to edit calendar event
                        } else {
                            // if user is a basic user, and does not have rights to publish calendar events then the user should not have access to the link
                            if (($user['role'] == 3) && ($user['publish_calendar_events'] == false)) {
                                $link_access = false;
                            }
                        }
                        // Else we are working in the front-end (calendar view page type)
                    } else {
                        // If the event is recurring.
                        if ($event['recurrence_number']) {
                            $recurring_query_string = '&recurrence_number=' . $event['recurrence_number'];
                        }
                    }
                    $output_availability_icon = '';
                    // if this event is published
                    // and it does not have an exception
                    // and reservations are enabled
                    // and the reservation product still exists
                    // and the reservation product is enabled
                    // then determine which availability icon we should show
                    if (($event['published'] == 1) && ($event['hidden_exception'] == 0) && ($event['reservations'] == 1) && ($calendar_event['product_id'] != '') && ($calendar_event['product_enabled'] == 1)) {
                        // if the event is not in the past
                        // and reservations are not limited
                        // or the number of remaining spots is greater than 0
                        // and inventory is disabled for product
                        // or the inventory quantity is greater than 0
                        // or the product is allowed to be backordered
                        // then this calendar event is available, so output available icon
                        if ((strtotime($calendar_event['end_date_and_time']) >= time()) && (($calendar_event['limit_reservations'] == 0) || ($calendar_event['number_of_remaining_spots'] > 0)) && (($calendar_event['inventory'] == 0) || ($calendar_event['inventory_quantity'] > 0) || ($calendar_event['backorder'] == 1))) {
                            $output_availability_icon = '<span style="color: green; font-weight: bold; font-size: 130%; margin-right: .25em">&#9679;</span>';
                            // else this calendar event is not available, so output unavailable icon
                        } else {
                            $output_availability_icon = '<span style="color: lightgrey; font-weight: bold; font-size: 130%; margin-right: .25em">&#9679;</span>';
                        }
                    }
                    // If there is a page to link and the user has access to the link, then prepare to output the event name with a link
                    if ($link && ($link_access == true)) {
                        $output_link = '<a href="' . h(escape_url($link)) . '?id=' . h($event['id']) . h($recurring_query_string) . '">' . $output_availability_icon . '<strong>' . h($event['name']) . '</strong></a><br>';
                        // Else there should not be a link, so prepare to just output the event name
                    } else {
                        $output_link = $output_availability_icon . '<strong>' . h($event['name']) . '</strong><br>';
                    }
                    $output_time = '';
                    // If there is a time range, then output it.
                    if ($calendar_event['time_range'] != '') {
                        $output_time = $calendar_event['time_range'] . '<br>';
                    }
                    $output_calendar_data .= '<p class="' . $output_calendar_classes . '">

                                ' . $start_strikethrough_tag . '

                                ' . $asterisk . $output_link . '

                                ' . $output_time . '

                                ' . $end_strikethrough_tag . '

                                ' . $exception_link . '

                                ' . h($event['short_description']) . '

                            </p>';
                    // if next event start date is not equal to current event start date, then close container for this day
                    if ($events[$key + 1]['event_start_date'] != $event['event_start_date']) {
                        $output_calendar_data .= '   </div>

                                </fieldset>';
                    }
                }
                // else there are no events, so prepare notice
            } else {
                $output_calendar_data = '<div>' . lang('There are no calendar events for your selection.') . '</div>';
            }

            $output_weekly_class = '';
            // if this is for a back-end view then set the update button class
            if ($user) {
                $output_weekly_class = ' p-2';
            }

            // if monthly or backend screen (null)
            if (($view == 'monthly') || ($view == '')) {
                // hide weekly output if monthly is present (and display using media queries for responsive designs)

                $output_calendar .= '<div class="weekly' . $output_weekly_class . '" style="display: none;">' . $output_calendar_data . '</div>';
            } else {
                $output_calendar .= '<div class="weekly' . $output_weekly_class . '">' . $output_calendar_data . '</div>';
            }
        }
        if ($output_minimal_calendar == true) {
            $output_update_calendar_view_form = '';
        }

        return '<div class="software_calendar">

                ' . $output_update_calendar_view_form . '

                ' . $output_calendar . '

            </div>';
        // else no calendars were supplied, so output error
    } else {
        // if this view is for the back-end
        if ($user) {
            return lang('There are no calendars, so no calendar events could be displayed.');
            // else this view is for the front-end
        } else {
            return lang('There are no calendars selected for this view, so no calendar events could be displayed.');
        }
    }
}
function check_calendar_event_location_availability($location_id, $event_start, $event_end, $event_id = "0")
{



    // Convert the timestamps to seconds
    $event_start = strtotime($event_start);
    $event_end = strtotime($event_end);
    // If they are editing an existing event, this should be > 0
    if ($event_id != "0") {
        $event_id_where_clause = ' AND (id != "' . escape($event_id) . '")';
    } else {
        $event_id_where_clause = '';
    }
    // get calendar event exceptions
    $query = "SELECT

            calendar_event_id,

            recurrence_number

        FROM calendar_event_exceptions";
    $result = mysqli_query(db::$con, $query);
    $calendar_event_exceptions = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $calendar_event_exceptions[] = $row;
    }
    // get calendar events
    $query = "SELECT

            id,

            calendar_events_calendar_event_locations_xref.calendar_event_location_id as calendar_event_location_id, 

            start_time as normal_start_time, 

            end_time as normal_end_time,

            UNIX_TIMESTAMP(start_time) as start_time, 

            UNIX_TIMESTAMP(end_time) as end_time,

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

            recurrence_month_type

        FROM calendar_events

        LEFT JOIN calendar_events_calendar_event_locations_xref ON calendar_events.id = calendar_events_calendar_event_locations_xref.calendar_event_id

        WHERE 

            (calendar_events_calendar_event_locations_xref.calendar_event_location_id = '" . escape($location_id) . "')" . $event_id_where_clause;
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // Check to see if there is an event using the same room at the same time
    while ($row = mysqli_fetch_assoc($result)) {
        // If this is not a recurring event, then do a simple check
        if ($row['recurrence'] == 0) {
            $event_start_date_and_time = $row['start_time'];
            $event_end_date_and_time = $row['end_time'];
            if (($event_start_date_and_time <= $event_start) && ($event_end_date_and_time > $event_start)) {
                return $row['id'];
            }
            if (($event_start_date_and_time > $event_start) && ($event_start_date_and_time < $event_end)) {
                return $row['id'];
            }
            if (($event_end_date_and_time > $event_start) && ($event_end_date_and_time <= $event_end)) {
                return $row['id'];
            }
        } else {
            // This is a recurring event, check each day to make sure its not conflicting
            $event_start_date_and_time = $row['normal_start_time'];
            $event_end_date_and_time = $row['normal_end_time'];
            $recurrence_number = $row['recurrence_number'];
            $recurrence_type = $row['recurrence_type'];
            $recurrence_day_sun = $row['recurrence_day_sun'];
            $recurrence_day_mon = $row['recurrence_day_mon'];
            $recurrence_day_tue = $row['recurrence_day_tue'];
            $recurrence_day_wed = $row['recurrence_day_wed'];
            $recurrence_day_thu = $row['recurrence_day_thu'];
            $recurrence_day_fri = $row['recurrence_day_fri'];
            $recurrence_day_sat = $row['recurrence_day_sat'];
            $recurrence_month_type = $row['recurrence_month_type'];
            // split event start date and time into parts
            $event_start_date_and_time_parts = explode(' ', $event_start_date_and_time);
            $event_start_date = $event_start_date_and_time_parts[0];
            $event_start_time = $event_start_date_and_time_parts[1];
            // split event end date and time into parts
            $event_end_date_and_time_parts = explode(' ', $event_end_date_and_time);
            $event_end_time = $event_end_date_and_time_parts[1];
            $calendar_exceptions = array();
            // Split event start date into parts, that we will use later.
            $event_start_date_parts = explode('-', $event_start_date);
            $event_start_year = $event_start_date_parts[0];
            $event_start_month = $event_start_date_parts[1];
            $event_start_day = $event_start_date_parts[2];
            foreach ($calendar_event_exceptions as $calendar_event_exception) {
                if ($calendar_event_exception['calendar_event_id'] == $row['id']) {
                    $calendar_exceptions[] = $calendar_event_exception['recurrence_number'];
                }
            }
            // If this is a monthly event and the month type is "day of the week",
            // then determine which week in the month the event is on.
            // If the week is 1-4 then we will use that, however if the week is 5,
            // then we interpret that as the last week.
            if (($recurrence_type == 'month') && ($recurrence_month_type == 'day_of_the_week')) {
                $day_of_the_week = date('l', strtotime($event_start_date));
                $first_day_of_the_month_timestamp = strtotime($event_start_year . '-' . $event_start_month . '-01');
                $week = '';
                // Create a loop in order to determine which week event falls on.
                // We only loop through 4 weeks, because we are going to set "last" below for 5th week.
                for ($week_index = 0; $week_index <= 3; $week_index++) {
                    // If the event is in this week, then remember the week number and break out of this loop.
                    if ($event_start_date == date('Y-m-d', strtotime('+' . $week_index . ' week ' . $day_of_the_week, $first_day_of_the_month_timestamp))) {
                        $week = $week_index + 1;
                        break;
                    }
                }
                // If a week was not found, then that means it falls on the 5th week,
                // so set it to be the last week.
                if ($week == '') {
                    $week = 'last';
                }
            }
            // loop in order to create a new event for each recurrence
            for ($i = 0; $i <= $recurrence_number; $i++) {
                // We will use this variable to stop the calculations if the recurrence number is hidden by an exception
                $halt_on_exception = '0';
                if (count($calendar_exceptions) > 0) {
                    if (in_array($i, $calendar_exceptions)) {
                        $halt_on_exception = '1';
                    } else {
                        $halt_on_exception = '0';
                    }
                }
                // if recurrence number is greater than 0, then adjust event start date
                if ($i > 0) {
                    // adjust event start date depending on recurrence type
                    switch ($recurrence_type) {
                        // Daily
                        case 'day':
                            $count = 0;
                            // Loop through days in the future until we find a date that is valid
                            // based on the valid days of the week that were selected.
                            while (true) {
                                $new_time = strtotime('+1 day', strtotime($event_start_date));
                                $event_start_date = date('Y-m-d', $new_time);
                                $day_of_the_week = strtolower(date('D', $new_time));
                                // If this day of the week is valid for this calendar event,
                                // then we have found a valid date, so break out of the loop.
                                if (${'recurrence_day_' . $day_of_the_week} == 1) {
                                    break;
                                }
                                $count++;
                                // If we have already looped 7 times, then something is wrong,
                                // so break out of this loop and the recurrence loop above.
                                // This should never happen but is added just in case in order to
                                // prevent an endless loop.
                                if ($count == 7) {
                                    break 3;
                                }
                            }
                            break;
                        // Weekly
                        case 'week':
                            $new_time = mktime(0, 0, 0, $event_start_month, $event_start_day + (7 * $i), $event_start_year);
                            $event_start_date = date('Y', $new_time) . '-' . date('m', $new_time) . '-' . date('d', $new_time);
                            break;
                        // Monthly
                        case 'month':
                            switch ($recurrence_month_type) {
                                case 'day_of_the_month':
                                    $new_time = mktime(0, 0, 0, $event_start_month + $i, 1, $event_start_year);
                                    $new_event_start_year = date('Y', $new_time);
                                    $new_event_start_month = date('m', $new_time);
                                    $new_event_start_day = $event_start_day;
                                    // if date is not valid, then get last date for month
                                    if (checkdate($new_event_start_month, $new_event_start_day, $new_event_start_year) == false) {
                                        $new_event_start_day = date('t', mktime(0, 0, 0, $new_event_start_month, 1, $new_event_start_year));
                                    }
                                    $event_start_date = $new_event_start_year . '-' . $new_event_start_month . '-' . $new_event_start_day;
                                    break;
                                case 'day_of_the_week':
                                    $first_day_of_the_month_timestamp = mktime(0, 0, 0, $event_start_month + $i, 1, $event_start_year);
                                    // If the week is 1-4 then find the date in a certain way.
                                    if ($week != 'last') {
                                        $week_index = $week - 1;
                                        $new_time = strtotime('+' . $week_index . ' week ' . $day_of_the_week, $first_day_of_the_month_timestamp);
                                        // Otherwise the week is last, so find the date in a different way.
                                    } else {
                                        $last_day_of_the_month_timestamp = strtotime(date('Y-m-t', $first_day_of_the_month_timestamp));
                                        // If the last day of the month happens to be the right day of the week,
                                        // then thats that day that we want.
                                        if (date('l', $last_day_of_the_month_timestamp) == $day_of_the_week) {
                                            $new_time = $last_day_of_the_month_timestamp;
                                            // Otherwise find the day of the week that we want in the last week of the month.
                                        } else {
                                            $new_time = strtotime('last ' . $day_of_the_week, $last_day_of_the_month_timestamp);
                                        }
                                    }
                                    $event_start_date = date('Y-m-d', $new_time);
                                    break;
                            }
                            break;
                        // Yearly
                        case 'year':
                            $new_event_start_year = $event_start_year + $i;
                            $new_event_start_month = $event_start_month;
                            $new_event_start_day = $event_start_day;
                            // if date is not valid, then get last date for month
                            if (checkdate($new_event_start_month, $new_event_start_day, $new_event_start_year) == false) {
                                $new_event_start_day = date('t', mktime(0, 0, 0, $new_event_start_month, 1, $new_event_start_year));
                            }
                            $event_start_date = $new_event_start_year . '-' . $new_event_start_month . '-' . $new_event_start_day;
                            break;
                    }
                }
                $event_start_date_and_time = strtotime($event_start_date . ' ' . $event_start_time);
                $event_end_date_and_time = strtotime($event_start_date . ' ' . $event_end_time);
                // if the event start date is greater than or equal to stop date, then break out of loop,
                // because it is not possible for further recurrences to be in date range
                if ($event_start_date_and_time >= $event_end) {
                    break;
                }
                if ($halt_on_exception == '0') {
                    if (($event_start_date_and_time <= $event_start) && ($event_end_date_and_time > $event_start)) {
                        return $row['id'];
                    }
                    if (($event_start_date_and_time > $event_start) && ($event_start_date_and_time < $event_end)) {
                        return $row['id'];
                    }
                    if (($event_end_date_and_time > $event_start) && ($event_end_date_and_time <= $event_end)) {
                        return $row['id'];
                    }
                }
            }
        }
    }
    // If there was not an overlapped booking, then the room is available.
    return 'available';
}
// create function that gets data for a calendar event and unique data for a recurring instance
function get_calendar_event($event_id, $recurrence_number)
{
    // get calendar event data
    $query = "SELECT

            calendar_events.id,

            calendar_events.name,

            calendar_events.published,

            calendar_events.unpublish_days,

            calendar_events.short_description,

            calendar_events.full_description,

            calendar_events.notes,

            calendar_events.all_day,

            calendar_events.start_time,

            calendar_events.end_time,

            calendar_events.show_start_time,

            calendar_events.show_end_time,

            calendar_events.recurrence,

            calendar_events.recurrence_number as total_recurrence_number,

            calendar_events.recurrence_type,

            calendar_events.recurrence_day_sun,

            calendar_events.recurrence_day_mon,

            calendar_events.recurrence_day_tue,

            calendar_events.recurrence_day_wed,

            calendar_events.recurrence_day_thu,

            calendar_events.recurrence_day_fri,

            calendar_events.recurrence_day_sat,

            calendar_events.recurrence_month_type,

            calendar_events.location,

            calendar_events.reservations,

            calendar_events.separate_reservations,

            calendar_events.limit_reservations,

            calendar_events.number_of_initial_spots,

            calendar_events.no_remaining_spots_message,

            calendar_events.reserve_button_label,

            calendar_events.next_page_id,

            products.id as product_id,

            products.enabled AS product_enabled,

            products.inventory,

            products.inventory_quantity,

            products.backorder,

            products.out_of_stock_message,

            calendar_events.created_timestamp

        FROM calendar_events

        LEFT JOIN products ON calendar_events.product_id = products.id

        WHERE calendar_events.id = '" . escape($event_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if calendar event could not be found, then return false
    if (mysqli_num_rows($result) == 0) {
        return false;
    }
    $row = mysqli_fetch_assoc($result);
    $id = $row['id'];
    $name = $row['name'];
    $published = $row['published'];
    $unpublish_days = $row['unpublish_days'];
    $short_description = $row['short_description'];
    $full_description = $row['full_description'];
    $notes_content = $row['notes'];
    $all_day = $row['all_day'];
    $start_date_and_time = $row['start_time'];
    $end_date_and_time = $row['end_time'];
    $show_start_time = $row['show_start_time'];
    $show_end_time = $row['show_end_time'];
    $recurrence = $row['recurrence'];
    $total_recurrence_number = $row['total_recurrence_number'];
    $recurrence_type = $row['recurrence_type'];
    $recurrence_day_sun = $row['recurrence_day_sun'];
    $recurrence_day_mon = $row['recurrence_day_mon'];
    $recurrence_day_tue = $row['recurrence_day_tue'];
    $recurrence_day_wed = $row['recurrence_day_wed'];
    $recurrence_day_thu = $row['recurrence_day_thu'];
    $recurrence_day_fri = $row['recurrence_day_fri'];
    $recurrence_day_sat = $row['recurrence_day_sat'];
    $recurrence_month_type = $row['recurrence_month_type'];
    $location = $row['location'];
    $reservations = $row['reservations'];
    $separate_reservations = $row['separate_reservations'];
    $limit_reservations = $row['limit_reservations'];
    $number_of_initial_spots = $row['number_of_initial_spots'];
    $no_remaining_spots_message = $row['no_remaining_spots_message'];
    $reserve_button_label = $row['reserve_button_label'];
    $next_page_id = $row['next_page_id'];
    $product_id = $row['product_id'];
    $product_enabled = $row['product_enabled'];
    $inventory = $row['inventory'];
    $inventory_quantity = $row['inventory_quantity'];
    $backorder = $row['backorder'];
    $out_of_stock_message = $row['out_of_stock_message'];
    $created_timestamp = $row['created_timestamp'];
    $number_of_remaining_spots = 0;
    // if reservations is enabled and limit reservations is enabled, then prepare to get number of remaining spots
    if (($reservations == 1) && ($limit_reservations == 1)) {
        // if this is a recurring event and reservations are separated for each recurring instance
        // then prepare to look for remaining spots for this recurrence number
        if (($recurrence == 1) && ($separate_reservations == 1)) {
            $sql_recurrence_number = $recurrence_number;
            // else this is not a recurring event or reservations are not separated so look for remaining spots for the 0 recurrence number
        } else {
            $sql_recurrence_number = 0;
        }
        // get number of remaining spots for this calendar event and recurrence number
        $query = "SELECT number_of_remaining_spots

            FROM remaining_reservation_spots

            WHERE

                (calendar_event_id = '" . escape($event_id) . "')

                AND (recurrence_number = '" . escape($sql_recurrence_number) . "')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // if a remaining spots record was found then use it to populate the remaining spots
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $number_of_remaining_spots = $row['number_of_remaining_spots'];
            // else a remaining spots record was not found, so use initial spots
        } else {
            $number_of_remaining_spots = $number_of_initial_spots;
        }
    }
    // Get the locations that this event will use in alphabetical order.
    $query = "SELECT

            calendar_event_locations.name

        FROM calendar_events_calendar_event_locations_xref

        LEFT JOIN calendar_event_locations ON calendar_events_calendar_event_locations_xref.calendar_event_location_id = calendar_event_locations.id

        WHERE calendar_event_id = '" . escape($event_id) . "'

        ORDER BY calendar_event_locations.name";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $calendar_locations = "";
    // Add each location to the main location list that will be output to the front end soon
    while ($row = mysqli_fetch_assoc($result)) {
        if ($calendar_locations) {
            $calendar_locations .= ', ';
        }
        $calendar_locations .= $row['name'];
    }
    // if a calendar event location with an id was not selected, then use the custom location that was manually entered
    if ($calendar_locations == '') {
        $location_content = $location;
        // else a calendar event location with an id was selected
    } else {
        // If the user entered a custom location
        if ($location != '') {
            // Add it to the end, prefixed with a comman and space.
            $location_content = $calendar_locations . ', ' . $location;
            // Do not worry about it.
        } else {
            $location_content = $calendar_locations;
        }
    }
    // if there is location content, then prepare to output location
    if ($location_content) {
        $location = $location_content;
    }
    // split start date and time in order to get date and time
    $start_date_and_time_parts = explode(' ', $start_date_and_time);
    $start_date = $start_date_and_time_parts[0];
    $start_time = $start_date_and_time_parts[1];
    // split end date and time in order to get date and time
    $end_date_and_time_parts = explode(' ', $end_date_and_time);
    $end_date = $end_date_and_time_parts[0];
    $end_time = $end_date_and_time_parts[1];
    // if this is a recurring event and this is a recurring instance,
    // then adjust start date & time and end date & time for this recurring instance
    if (($recurrence == 1) && ($recurrence_number)) {
        // Split the date into more precise values
        $event_start_date_parts = explode('-', $start_date);
        $event_start_year = $event_start_date_parts[0];
        $event_start_month = $event_start_date_parts[1];
        $event_start_day = $event_start_date_parts[2];
        // Find the difference between the original start date and end date (So we do not need to calculate the end dates new date too)
        $event_start_end_difference = (strtotime($end_date_and_time) - strtotime($start_date_and_time));
        switch ($recurrence_type) {
            // Daily
            case 'day':
                $event_start_date = $start_date;
                // Loop through all recurrence numbers in order to
                // calculate the date for this recurrence number.
                for ($i = 1; $i <= $recurrence_number; $i++) {
                    $count = 0;
                    // Loop through days in the future until we find a date that is valid
                    // based on the valid days of the week that were selected.
                    while (true) {
                        $new_start_time = strtotime('+1 day', strtotime($event_start_date));
                        $event_start_date = date('Y-m-d', $new_start_time);
                        $day_of_the_week = strtolower(date('D', $new_start_time));
                        // If this day of the week is valid for this calendar event,
                        // then we have found a valid date, so break out of the loop.
                        if (${'recurrence_day_' . $day_of_the_week} == 1) {
                            break;
                        }
                        $count++;
                        // If we have already looped 7 times, then something is wrong,
                        // so break out of this loop and the recurrence loop above.
                        // This should never happen but is added just in case in order to
                        // prevent an endless loop.
                        if ($count == 7) {
                            break 2;
                        }
                    }
                }
                break;
            // Weekly
            case 'week':
                $new_start_time = mktime(0, 0, 0, $event_start_month, $event_start_day + (7 * $recurrence_number), $event_start_year);
                $event_start_date = date('Y', $new_start_time) . '-' . date('m', $new_start_time) . '-' . date('d', $new_start_time);
                break;
            // Monthly
            case 'month':
                switch ($recurrence_month_type) {
                    case 'day_of_the_month':
                        $new_start_time = mktime(0, 0, 0, $event_start_month + $recurrence_number, 1, $event_start_year);
                        $new_event_start_year = date('Y', $new_start_time);
                        $new_event_start_month = date('m', $new_start_time);
                        $new_event_start_day = $event_start_day;
                        // if date is not valid, then get last date for month
                        if (checkdate($new_event_start_month, $new_event_start_day, $new_event_start_year) == false) {
                            $new_event_start_day = date('t', mktime(0, 0, 0, $new_event_start_month, 1, $new_event_start_year));
                        }
                        $event_start_date = $new_event_start_year . '-' . $new_event_start_month . '-' . $new_event_start_day;
                        break;
                    case 'day_of_the_week':
                        // Determine which week in the month the event is on.
                        // If the week is 1-4 then we will use that, however if the week is 5,
                        // then we interpret that as the last week.
                        $day_of_the_week = date('l', strtotime($start_date));
                        $first_day_of_the_month_timestamp = strtotime($event_start_year . '-' . $event_start_month . '-01');
                        $week = '';
                        // Create a loop in order to determine which week event falls on.
                        // We only loop through 4 weeks, because we are going to set "last" below for 5th week.
                        for ($week_index = 0; $week_index <= 3; $week_index++) {
                            // If the event is in this week, then remember the week number and break out of this loop.
                            if ($start_date == date('Y-m-d', strtotime('+' . $week_index . ' week ' . $day_of_the_week, $first_day_of_the_month_timestamp))) {
                                $week = $week_index + 1;
                                break;
                            }
                        }
                        // If a week was not found, then that means it falls on the 5th week,
                        // so set it to be the last week.
                        if ($week == '') {
                            $week = 'last';
                        }
                        $first_day_of_the_month_timestamp = mktime(0, 0, 0, $event_start_month + $recurrence_number, 1, $event_start_year);
                        // If the week is 1-4 then find the date in a certain way.
                        if ($week != 'last') {
                            $week_index = $week - 1;
                            $new_start_time = strtotime('+' . $week_index . ' week ' . $day_of_the_week, $first_day_of_the_month_timestamp);
                            // Otherwise the week is last, so find the date in a different way.
                        } else {
                            $last_day_of_the_month_timestamp = strtotime(date('Y-m-t', $first_day_of_the_month_timestamp));
                            // If the last day of the month happens to be the right day of the week,
                            // then thats that day that we want.
                            if (date('l', $last_day_of_the_month_timestamp) == $day_of_the_week) {
                                $new_start_time = $last_day_of_the_month_timestamp;
                                // Otherwise find the day of the week that we want in the last week of the month.
                            } else {
                                $new_start_time = strtotime('last ' . $day_of_the_week, $last_day_of_the_month_timestamp);
                            }
                        }
                        $event_start_date = date('Y-m-d', $new_start_time);
                        break;
                }
                break;
            // Yearly
            case 'year':
                $new_event_start_year = $event_start_year + $recurrence_number;
                $new_event_start_month = $event_start_month;
                $new_event_start_day = $event_start_day;
                // if date is not valid, then get last date for month
                if (checkdate($new_event_start_month, $new_event_start_day, $new_event_start_year) == false) {
                    $new_event_start_day = date('t', mktime(0, 0, 0, $new_event_start_month, 1, $new_event_start_year));
                }
                $event_start_date = $new_event_start_year . '-' . $new_event_start_month . '-' . $new_event_start_day;
                break;
        }
        // Save the new start date and new end date to the variables we were using previously.
        $start_date_and_time = $event_start_date . ' ' . $start_time;
        $end_date_and_time = date('Y-m-d H:i:s', (strtotime($start_date_and_time) + $event_start_end_difference));
    }
    // If the date format is month and then day, then use that format.
    if (DATE_FORMAT == 'month_day') {
        $month_and_day_format = 'F j';
        // Otherwise the date format is day and then month, so use that format.
    } else {
        $month_and_day_format = 'j F';
    }
    $date_and_time_range = '';
    $time_range = '';
    // If the time format is twelve hour system, then use that format.
    if (TIME_FORMAT == 'twelve_hours') {
        $hour_system_format = 'g:i A';
        // Otherwise the time format is twenty hour system, so use that format.
    } else {
        $hour_system_format = 'H:i';
    }
    // If this is not an all day event then deal with both dates & times.
    if ($all_day == 0) {
        // if start date is equal to end date, then date and time range and time range in a certain way
        if ($start_date == $end_date) {
            // If the start and end times should both be shown, then do that.
            if ($show_start_time && $show_end_time) {
                $date_and_time_range = date('l, ' . $month_and_day_format . ', Y ' . $hour_system_format, strtotime($start_date_and_time)) . ' - ' . date($hour_system_format, strtotime($end_date_and_time));
                $time_range = prepare_form_data_for_output($start_time, 'time') . ' - ' . prepare_form_data_for_output($end_time, 'time');
                // Otherwise if both the start and end times should not be shown, then do that.
            } else if (!$show_start_time && !$show_end_time) {
                $date_and_time_range = date('l, ' . $month_and_day_format . ', Y', strtotime($start_date_and_time));
                $time_range = '';
                // Otherwise if only the start time should be shown, then do that.
            } else if ($show_start_time && !$show_end_time) {
                $date_and_time_range = date('l, ' . $month_and_day_format . ', Y g:i A', strtotime($start_date_and_time));
                $time_range = prepare_form_data_for_output($start_time, 'time');
                // Otherwise if only the end time should be shown, then do that.
            } else if (!$show_start_time && $show_end_time) {
                $date_and_time_range = date('l, ' . $month_and_day_format . ', Y', strtotime($start_date_and_time)) . ' (ends at ' . date('g:i A', strtotime($end_date_and_time)) . ')';
                $time_range = 'Ends at ' . prepare_form_data_for_output($end_time, 'time');
            }
            // else start date is not equal to end date, so prepare date and time range and time range in a certain way
        } else {
            // If the start and end times should both be shown, then do that.
            if ($show_start_time && $show_end_time) {
                $date_and_time_range = date('l, ' . $month_and_day_format . ', Y ' . $hour_system_format, strtotime($start_date_and_time)) . ' - ' . date('l, ' . $month_and_day_format . ', Y ' . $hour_system_format, strtotime($end_date_and_time));
                $time_range = prepare_form_data_for_output($start_time, 'time');
                // Otherwise if both the start and end times should not be shown, then do that.
            } else if (!$show_start_time && !$show_end_time) {
                $date_and_time_range = date('l, ' . $month_and_day_format . ', Y', strtotime($start_date_and_time)) . ' - ' . date('l, ' . $month_and_day_format . ', Y', strtotime($end_date_and_time));
                $time_range = '';
                // Otherwise if only the start time should be shown, then do that.
            } else if ($show_start_time && !$show_end_time) {
                $date_and_time_range = date('l, ' . $month_and_day_format . ', Y ' . $hour_system_format, strtotime($start_date_and_time)) . ' - ' . date('l, ' . $month_and_day_format . ', Y', strtotime($end_date_and_time));
                $time_range = prepare_form_data_for_output($start_time, 'time');
                // Otherwise if only the end time should be shown, then do that.
            } else if (!$show_start_time && $show_end_time) {
                $date_and_time_range = date('l, ' . $month_and_day_format . ', Y', strtotime($start_date_and_time)) . ' - ' . date('l, ' . $month_and_day_format . ', Y ' . $hour_system_format, strtotime($end_date_and_time));
                $time_range = '';
            }
        }
        // Otherwise this is an all day event, so just deal with dates.
    } else {
        // if start date is equal to end date, then prepare date and time range with only one date
        if ($start_date == $end_date) {
            $date_and_time_range = date('l, ' . $month_and_day_format . ', Y', strtotime($start_date_and_time));
            $time_range = '';
            // else start date is not equal to end date, so prepare date and time range with two dates
        } else {
            $date_and_time_range = date('l, ' . $month_and_day_format . ', Y', strtotime($start_date_and_time)) . ' - ' . date('l, ' . $month_and_day_format . ', Y', strtotime($end_date_and_time));
            $time_range = '';
        }
    }
    // return array
    return array(
        'id' => $id,
        'name' => $name,
        'published' => $published,
        'unpublish_days' => $unpublish_days,
        'short_description' => $short_description,
        'full_description' => $full_description,
        'notes_content' => $notes_content,
        'all_day' => $all_day,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'start_date_and_time' => $start_date_and_time,
        'end_date_and_time' => $end_date_and_time,
        'date_and_time_range' => $date_and_time_range,
        'time_range' => $time_range,
        'recurrence' => $recurrence,
        'total_recurrence_number' => $total_recurrence_number,
        'recurrence_type' => $recurrence_type,
        'recurrence_month_type' => $recurrence_month_type,
        'location' => $location,
        'reservations' => $reservations,
        'separate_reservations' => $separate_reservations,
        'limit_reservations' => $limit_reservations,
        'number_of_initial_spots' => $number_of_initial_spots,
        'number_of_remaining_spots' => $number_of_remaining_spots,
        'no_remaining_spots_message' => $no_remaining_spots_message,
        'reserve_button_label' => $reserve_button_label,
        'next_page_id' => $next_page_id,
        'product_id' => $product_id,
        'product_enabled' => $product_enabled,
        'inventory' => $inventory,
        'inventory_quantity' => $inventory_quantity,
        'backorder' => $backorder,
        'out_of_stock_message' => $out_of_stock_message,
        'created_timestamp' => $created_timestamp
    );
}

// Create function that can be used to build the options for a pick list of calendar events.
// Properties:
// reservations: true/false. Used to get events that have reservations enabled or disabled.
// Don't pass property in order to get all calendar events.
function get_calendar_event_options($properties)
{
    $reservations = $properties['reservations'];
    $calendar_event_options = array();
    // Add first blank option.
    $calendar_event_options[] = array(
        'label' => '-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('calendar event')))) . '-',
        'value' => ''
    );
    $sql_reservations = "";
    // If a reservations property was passed, then filter calendar events.
    if (isset($properties['reservations']) == true) {
        if ($reservations == true) {
            $sql_reservations = "WHERE reservations = '1'";
        } else {
            $sql_reservations = "WHERE reservations = '0'";
        }
    }
    // Get calendar events in order to prepare options.
    $calendar_events = db_items("SELECT

            id,

            name,

            short_description

        FROM calendar_events

        $sql_reservations

        ORDER BY name ASC");
    // Loop through the events in order to prepare options.
    foreach ($calendar_events as $calendar_event) {
        // If this user has access to this calendar event, then include it.
        if (validate_calendar_event_access($calendar_event['id']) == true) {
            $output_label = h($calendar_event['name']);
            // If there is a short description and it is not the same as the name, then add it to the label.
            if (($calendar_event['short_description'] != '') && ($calendar_event['short_description'] != $calendar_event['name'])) {
                $output_label .= ' - ' . h($calendar_event['short_description']);
            }
            $calendar_event_options[] = array(
                'label' => $output_label,
                'value' => $calendar_event['id']
            );
        }
    }
    return $calendar_event_options;
}
