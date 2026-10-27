<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The calendar event form, shared by the create and the edit screens.
 *
 * Both screens check a submitted event the same way: the schedule (all day or
 * date and time), the repeat settings, the chosen locations against the other
 * events booked there for every repeating instance, at least one calendar the
 * user may post to, and the reservation fields. That check used to be written
 * out twice; add_calendar_event.php and edit_calendar_event.php now call
 * pg_calendar_event_form_validate(), and the edit screen passes the event's id
 * so the event does not collide with itself and its cancelled instances
 * (calendar_event_exceptions) are not checked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The start date of every instance of a repeating event.
 *
 * Instance 0 is the event itself; instances 1 to $recurrence_number follow by
 * day (only on the chosen weekdays), week, month (same day of the month, or
 * the same weekday of the same week, the fifth week read as the last) or year.
 * A day of the month the target month lacks becomes that month's last day.
 * When no weekday is chosen for a daily repeat the list stops at the first
 * instance that cannot be placed.
 *
 * @param string $start_date        'Y-m-d'
 * @param mixed  $recurrence_number The number of repeats (compared, not cast)
 * @param string $recurrence_type   'day', 'week', 'month' or 'year'
 * @param array  $days              'sun' ... 'sat' => 1 when the daily repeat runs that day
 * @param string $month_type        'day_of_the_month' or 'day_of_the_week'
 * @return array  Instance number => start date
 */
function pg_calendar_event_recurrence_dates($start_date, $recurrence_number, $recurrence_type, $days, $month_type)
{
    $event_start_date = $start_date;
    $dates = array();

    if ($recurrence_number > 0) {
        $event_start_date_parts = explode('-', $event_start_date);
        $event_start_year = $event_start_date_parts[0];
        $event_start_month = $event_start_date_parts[1];
        $event_start_day = $event_start_date_parts[2];

        // A monthly "day of the week" repeat keeps the week of the month the
        // event is in: 1 to 4, or the last week when it falls on the fifth.
        if (($recurrence_type == 'month') && ($month_type == 'day_of_the_week')) {
            $day_of_the_week = date('l', strtotime($event_start_date));
            $first_day_of_the_month_timestamp = strtotime($event_start_year . '-' . $event_start_month . '-01');

            $week = '';

            for ($week_index = 0; $week_index <= 3; $week_index++) {
                if ($event_start_date == date('Y-m-d', strtotime('+' . $week_index . ' week ' . $day_of_the_week, $first_day_of_the_month_timestamp))) {
                    $week = $week_index + 1;
                    break;
                }
            }

            if ($week == '') {
                $week = 'last';
            }
        }
    }

    for ($i = 0; $i <= $recurrence_number; $i++) {
        if ($i > 0) {
            switch ($recurrence_type) {
                case 'day':
                    $count = 0;

                    // Move on day by day to the next chosen weekday; seven
                    // misses mean no weekday is chosen, so stop the list.
                    while (true) {
                        $new_time = strtotime('+1 day', strtotime($event_start_date));
                        $event_start_date = date('Y-m-d', $new_time);
                        $day_of_the_week = strtolower(date('D', $new_time));

                        if (isset($days[$day_of_the_week]) && ($days[$day_of_the_week] == 1)) {
                            break;
                        }

                        $count++;

                        if ($count == 7) {
                            return $dates;
                        }
                    }

                    break;

                case 'week':
                    $new_time = mktime(0, 0, 0, $event_start_month, $event_start_day + (7 * $i), $event_start_year);
                    $event_start_date = date('Y', $new_time) . '-' . date('m', $new_time) . '-' . date('d', $new_time);
                    break;

                case 'month':
                    switch ($month_type) {
                        case 'day_of_the_month':
                            $new_time = mktime(0, 0, 0, $event_start_month + $i, 1, $event_start_year);
                            $new_event_start_year = date('Y', $new_time);
                            $new_event_start_month = date('m', $new_time);
                            $new_event_start_day = $event_start_day;

                            if (checkdate($new_event_start_month, $new_event_start_day, $new_event_start_year) == false) {
                                $new_event_start_day = date('t', mktime(0, 0, 0, $new_event_start_month, 1, $new_event_start_year));
                            }

                            $event_start_date = $new_event_start_year . '-' . $new_event_start_month . '-' . $new_event_start_day;
                            break;

                        case 'day_of_the_week':
                            $first_day_of_the_month_timestamp = mktime(0, 0, 0, $event_start_month + $i, 1, $event_start_year);

                            if ($week != 'last') {
                                $week_index = $week - 1;
                                $new_time = strtotime('+' . $week_index . ' week ' . $day_of_the_week, $first_day_of_the_month_timestamp);
                            } else {
                                $last_day_of_the_month_timestamp = strtotime(date('Y-m-t', $first_day_of_the_month_timestamp));

                                if (date('l', $last_day_of_the_month_timestamp) == $day_of_the_week) {
                                    $new_time = $last_day_of_the_month_timestamp;
                                } else {
                                    $new_time = strtotime('last ' . $day_of_the_week, $last_day_of_the_month_timestamp);
                                }
                            }

                            $event_start_date = date('Y-m-d', $new_time);
                            break;
                    }

                    break;

                case 'year':
                    $new_event_start_year = $event_start_year + $i;
                    $new_event_start_month = $event_start_month;
                    $new_event_start_day = $event_start_day;

                    if (checkdate($new_event_start_month, $new_event_start_day, $new_event_start_year) == false) {
                        $new_event_start_day = date('t', mktime(0, 0, 0, $new_event_start_month, 1, $new_event_start_year));
                    }

                    $event_start_date = $new_event_start_year . '-' . $new_event_start_month . '-' . $new_event_start_day;
                    break;
            }
        }

        $dates[$i] = $event_start_date;
    }

    return $dates;
}

/**
 * Marks a location as taken when another event holds it during the given time.
 *
 * The other event is named only when the user may open it.
 *
 * @return bool  True when the location is taken
 */
function pg_calendar_event_form_check_location($liveform, $calendar_event_location, $start, $end, $calendar_event_id)
{
    $check_availability = check_calendar_event_location_availability($calendar_event_location['id'], $start, $end, escape($calendar_event_id));

    if ($check_availability == 'available') {
        return false;
    }

    if (validate_calendar_event_access($check_availability) == false) {
        $existing_calendar_name_statement = '';
    } else {
        $result = db_q("SELECT name FROM calendar_events WHERE id = ?", array((string) $check_availability)) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $existing_calendar_name_statement = lang(array('string'=>' by the event, {var:1}','vars'=>h($row['name'])));
    }

    $liveform->mark_error('calendar_event_location_' . $calendar_event_location['id'], lang(array('string'=>'The location, {var:1}, is already in use during this time{var:2}. Please choose a different time or location.','vars'=>array(h($calendar_event_location['name']),$existing_calendar_name_statement) )) );

    return true;
}

/**
 * Checks a submitted calendar event and marks every error on the form.
 *
 * @param liveform $liveform          The submitted form (fields in session)
 * @param string   $calendar_event_id The event being edited, '0' for a new one
 * @return array  'start_time' and 'end_time' as entered (an all day event gets
 *                12:00 AM and 11:59 PM; null when they have an error),
 *                'calendars' every calendar's id, and
 *                'calendar_event_locations' every location (id, name; empty
 *                when the schedule has an error)
 */
function pg_calendar_event_form_validate($liveform, $calendar_event_id = '0')
{
    $start_time = null;
    $end_time = null;

    $liveform->validate_required_field('name', lang('Name is required.'));

    // An all day event is entered as two dates; a time is added for storage.
    if ($liveform->get_field_value('all_day') == 1) {
        $liveform->validate_required_field('start_time', lang('Start Date is required.'));
        $liveform->validate_required_field('end_time', lang('End Date is required.'));

        if (($liveform->check_field_error('start_time') == false) && (validate_date($liveform->get_field_value('start_time')) == false)) {
            $liveform->mark_error('start_time', lang('Please enter a valid start date.'));
        }

        if (($liveform->check_field_error('end_time') == false) && (validate_date($liveform->get_field_value('end_time')) == false)) {
            $liveform->mark_error('end_time', lang('Please enter a valid end date.'));
        }

        if (
            ($liveform->check_field_error('start_time') == false)
            && ($liveform->check_field_error('end_time') == false)
            && (prepare_form_data_for_input($liveform->get_field_value('end_time'), 'date') < prepare_form_data_for_input($liveform->get_field_value('start_time'), 'date'))
        ) {
            $liveform->mark_error('end_time', lang('Please enter an end date that is on or after the start date.'));
        }

        if (($liveform->check_field_error('start_time') == false) && ($liveform->check_field_error('end_time') == false)) {
            $start_time = $liveform->get_field_value('start_time') . ' 12:00 AM';
            $end_time = $liveform->get_field_value('end_time') . ' 11:59 PM';
        }

    } else {
        $liveform->validate_required_field('start_time', lang('Start Date &amp; Time is required.'));
        $liveform->validate_required_field('end_time', lang('End Date & Time is required.'));

        if (($liveform->check_field_error('start_time') == false) && (validate_date_and_time($liveform->get_field_value('start_time')) == false)) {
            $liveform->mark_error('start_time', lang('Please enter a valid start date & time.'));
        }

        if (($liveform->check_field_error('end_time') == false) && (validate_date_and_time($liveform->get_field_value('end_time')) == false)) {
            $liveform->mark_error('end_time', lang('Please enter a valid end date & time.'));
        }

        if (
            ($liveform->check_field_error('start_time') == false)
            && ($liveform->check_field_error('end_time') == false)
            && (prepare_form_data_for_input($liveform->get_field_value('end_time'), 'date and time') <= prepare_form_data_for_input($liveform->get_field_value('start_time'), 'date and time'))
        ) {
            $liveform->mark_error('end_time', lang('Please enter an end date & time that is after the start date & time.'));
        }

        if (($liveform->check_field_error('start_time') == false) && ($liveform->check_field_error('end_time') == false)) {
            $start_time = $liveform->get_field_value('start_time');
            $end_time = $liveform->get_field_value('end_time');
        }
    }

    if ($liveform->get_field_value('recurrence') == 1) {
        $liveform->validate_required_field('total_recurrence_number', lang('Repeat number is required when you enable Repeat.'));
        $liveform->validate_required_field('recurrence_type', lang('Repeat type is required when you enable Repeat.'));

        if (
            ($liveform->check_field_error('total_recurrence_number') == false)
            &&
            (
                (is_numeric($liveform->get_field_value('total_recurrence_number')) == false)
                || ($liveform->get_field_value('total_recurrence_number') <= 0)
            )
        ) {
            $liveform->mark_error('total_recurrence_number', lang('Please enter a valid number that is greater than 0 for the repeat number.'));
        }

        if (
            ($liveform->get_field_value('recurrence_type') == 'day')
            && ($liveform->get_field_value('recurrence_day_sun') == 0)
            && ($liveform->get_field_value('recurrence_day_mon') == 0)
            && ($liveform->get_field_value('recurrence_day_tue') == 0)
            && ($liveform->get_field_value('recurrence_day_wed') == 0)
            && ($liveform->get_field_value('recurrence_day_thu') == 0)
            && ($liveform->get_field_value('recurrence_day_fri') == 0)
            && ($liveform->get_field_value('recurrence_day_sat') == 0)
        ) {
            $liveform->mark_error('recurrence_days_of_the_week', lang('Please select at least one day of the week for the daily repeat.'));
        }

        if ($liveform->get_field_value('recurrence_type') == 'month') {
            $liveform->validate_required_field('recurrence_month_type', lang('Repeat month type is required.'));
        }
    }

    $calendar_event_locations = array();

    // Location conflicts are checked only on a valid schedule.
    if (
        ($liveform->check_field_error('start_time') == false)
        && ($liveform->check_field_error('end_time') == false)
        && ($liveform->check_field_error('total_recurrence_number') == false)
        && ($liveform->check_field_error('recurrence_type') == false)
        && ($liveform->check_field_error('recurrence_days_of_the_week') == false)
        && ($liveform->check_field_error('recurrence_month_type') == false)
    ) {
        $result = db_q("SELECT id, name FROM calendar_event_locations ORDER BY name") or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            $calendar_event_locations[] = $row;
        }

        $selected_locations = array();

        foreach ($calendar_event_locations as $calendar_event_location) {
            if ($liveform->get_field_value('calendar_event_location_' . $calendar_event_location['id']) == 1) {
                $selected_locations[] = $calendar_event_location;
            }
        }

        if (($selected_locations) && ($liveform->get_field_value('recurrence') == 0)) {
            foreach ($selected_locations as $calendar_event_location) {
                pg_calendar_event_form_check_location($liveform, $calendar_event_location, $start_time, $end_time, $calendar_event_id);
            }

        // A repeating event is checked instance by instance; on the edit
        // screen an instance cancelled on its own is skipped. A location
        // stops being checked for an instance at its first conflict there.
        } elseif ($selected_locations) {
            $calendar_event_exceptions = array();

            if ($calendar_event_id != '0') {
                $result = db_q("SELECT recurrence_number FROM calendar_event_exceptions WHERE calendar_event_id = ?", array((string) $calendar_event_id));

                while ($result && ($row = mysqli_fetch_assoc($result))) {
                    $calendar_event_exceptions[] = $row['recurrence_number'];
                }
            }

            $event_start_date_and_time_parts = explode(' ', prepare_form_data_for_input($start_time, 'date and time'));
            $event_end_date_and_time_parts = explode(' ', prepare_form_data_for_input($end_time, 'date and time'));
            $event_start_time = $event_start_date_and_time_parts[1];
            $event_end_time = $event_end_date_and_time_parts[1];

            $dates = pg_calendar_event_recurrence_dates(
                $event_start_date_and_time_parts[0],
                $liveform->get_field_value('total_recurrence_number'),
                $liveform->get_field_value('recurrence_type'),
                array(
                    'sun' => $liveform->get_field_value('recurrence_day_sun'),
                    'mon' => $liveform->get_field_value('recurrence_day_mon'),
                    'tue' => $liveform->get_field_value('recurrence_day_tue'),
                    'wed' => $liveform->get_field_value('recurrence_day_wed'),
                    'thu' => $liveform->get_field_value('recurrence_day_thu'),
                    'fri' => $liveform->get_field_value('recurrence_day_fri'),
                    'sat' => $liveform->get_field_value('recurrence_day_sat'),
                ),
                $liveform->get_field_value('recurrence_month_type'));

            foreach ($dates as $i => $event_start_date) {
                if (in_array($i, $calendar_event_exceptions)) {
                    continue;
                }

                foreach ($selected_locations as $calendar_event_location) {
                    if (pg_calendar_event_form_check_location($liveform, $calendar_event_location, $event_start_date . ' ' . $event_start_time, $event_start_date . ' ' . $event_end_time, $calendar_event_id)) {
                        break;
                    }
                }
            }
        }
    }

    $calendars = array();

    $result = db_q("SELECT id FROM calendars ORDER BY name") or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $calendars[] = $row;
    }

    $calendar_selected = false;

    foreach ($calendars as $calendar) {
        if (($liveform->get_field_value('calendar_' . $calendar['id']) == 1) && (validate_calendar_access($calendar['id']) == true)) {
            $calendar_selected = true;
            break;
        }
    }

    if ($calendar_selected == false) {
        $liveform->mark_error('', lang('Please select at least one calendar.'));
    }

    if ($liveform->get_field_value('reservations') == 1) {
        $liveform->validate_required_field('reserve_button_label', lang('Reserve Button Label is required.'));
        $liveform->validate_required_field('product_id', lang('Reservation Product is required.'));
        $liveform->validate_required_field('next_page_id', lang('Next Page is required.'));
    }

    return array(
        'start_time' => $start_time,
        'end_time' => $end_time,
        'calendars' => $calendars,
        'calendar_event_locations' => $calendar_event_locations,
    );
}
