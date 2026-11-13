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
 * The form's cards are drawn here too (pg_calendar_event_form_cards(), from
 * the lists of pg_calendar_event_form_choices()); the screens keep their
 * gates, the form tag, their hidden fields and the buttons.
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

// The largest count the nine-digit number fields take (repeats, spots).
if (!defined('PG_CALENDAR_EVENT_MAX_NUMBER')) {
    define('PG_CALENDAR_EVENT_MAX_NUMBER', 999999999);
}

/**
 * Whether a typed number is a whole number from $min to $max: digits only,
 * so a sign, a decimal separator or a letter is refused.
 *
 * @param mixed $value
 * @param int   $min
 * @param int   $max
 * @return bool
 */
function pg_calendar_event_whole_number_ok($value, $min, $max)
{
    if (!is_scalar($value)) {
        return false;
    }

    $value = trim((string) $value);

    if (($value === '') || !ctype_digit($value) || (strlen(ltrim($value, '0')) > 10)) {
        return false;
    }

    return ((int) $value >= $min) && ((int) $value <= $max);
}

/**
 * The event's start and end as the location check reads them: 'Y-m-d H:i',
 * from the values as entered in the site's date order (an all day event
 * carries 12:00 AM and 11:59 PM).
 *
 * @param string $start_time
 * @param string $end_time
 * @return array  'start' and 'end'
 */
function pg_calendar_event_form_check_window($start_time, $end_time)
{
    return array(
        'start' => prepare_form_data_for_input($start_time, 'date and time'),
        'end' => prepare_form_data_for_input($end_time, 'date and time'),
    );
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

    // The column lengths: a longer text was stored cut without an error.
    pg_calendar_form_check_lengths($liveform, array(
        'name' => array(lang('Name'), 100),
        'location' => array(lang('Location'), 100),
        'short_description' => array(lang('Short Description'), 255),
        'reserve_button_label' => array(lang('Reserve Button Label'), 50),
    ));

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

        // Only the types the calendar expands: another value was stored as ''
        // and drew the event again and again on its start day.
        if (
            ($liveform->check_field_error('recurrence_type') == false)
            && !in_array((string) $liveform->get_field_value('recurrence_type'), array('day', 'week', 'month', 'year'), true)
        ) {
            $liveform->mark_error('recurrence_type', lang('Repeat type is required when you enable Repeat.'));
        }

        // A whole number: '2.5' passed is_numeric() and was stored as 3.
        if (
            ($liveform->check_field_error('total_recurrence_number') == false)
            && !pg_calendar_event_whole_number_ok($liveform->get_field_value('total_recurrence_number'), 1, PG_CALENDAR_EVENT_MAX_NUMBER)
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

            if (
                ($liveform->check_field_error('recurrence_month_type') == false)
                && !in_array((string) $liveform->get_field_value('recurrence_month_type'), array('day_of_the_month', 'day_of_the_week'), true)
            ) {
                $liveform->mark_error('recurrence_month_type', lang('Repeat month type is required.'));
            }
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
            // The availability check reads the time with strtotime(), which
            // cannot read the site's day/month order: '15/12/2026 10:00' is
            // false and '05/12/2026' is read as 12 May.
            $check_window = pg_calendar_event_form_check_window($start_time, $end_time);

            foreach ($selected_locations as $calendar_event_location) {
                pg_calendar_event_form_check_location($liveform, $calendar_event_location, $check_window['start'], $check_window['end'], $calendar_event_id);
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

            $check_window = pg_calendar_event_form_check_window($start_time, $end_time);
            $event_start_date_and_time_parts = explode(' ', $check_window['start']);
            $event_end_date_and_time_parts = explode(' ', $check_window['end']);
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

    // The session runs without strict SQL mode, so a value its column
    // cannot hold was cut or rounded silently: 99999 unpublish days became
    // 65535 and -12 spots became 0.
    $whole_numbers = array();

    if ($liveform->get_field_value('published') == 1) {
        $whole_numbers['unpublish_days'] = array(lang('Unpublish'), 65535);
    }

    if (($liveform->get_field_value('reservations') == 1) && ($liveform->get_field_value('limit_reservations') == 1)) {
        $whole_numbers['number_of_initial_spots'] = array(lang('Number Of Initial Spots'), PG_CALENDAR_EVENT_MAX_NUMBER);
        $whole_numbers['number_of_remaining_spots'] = array(lang('Remaining Spots'), PG_CALENDAR_EVENT_MAX_NUMBER);
    }

    foreach ($whole_numbers as $field => $rule) {
        $value = trim((string) $liveform->get_field_value($field));

        // Empty: no unpublishing, or no spots.
        if (($value !== '') && !pg_calendar_event_whole_number_ok($value, 0, $rule[1])) {
            $liveform->mark_error($field, lang(array('string' => '{var:1} must be a whole number from {var:2} to {var:3}.', 'vars' => array($rule[0], '0', (string) $rule[1]))));
        }
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

/**
 * The calendars a new event starts on: the requested one (the calendar the
 * user came from) when it is offered, else the only offered calendar when
 * there is exactly one, else none. Without a ticked calendar the first save
 * is refused.
 *
 * @param array $offered_ids   ids of the calendars the user may post to
 * @param mixed $requested_id  ?calendar_id from the link, or null
 * @return array  ids (strings) to tick
 */
function pg_calendar_event_form_start_calendars(array $offered_ids, $requested_id)
{
    $offered_ids = array_map('strval', array_values($offered_ids));

    if (is_string($requested_id) && $requested_id !== '' && in_array($requested_id, $offered_ids, true)) {
        return array($requested_id);
    }

    if (count($offered_ids) == 1) {
        return $offered_ids;
    }

    return array();
}

/**
 * Every calendar and every event location (id, name, by name), the lists
 * the form's checkboxes are drawn from.
 *
 * @return array  'calendars' and 'calendar_event_locations'
 */
function pg_calendar_event_form_choices()
{
    $choices = array('calendars' => array(), 'calendar_event_locations' => array());

    $result = db_q("SELECT id, name FROM calendars ORDER BY name") or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $choices['calendars'][] = $row;
    }

    $result = db_q("SELECT id, name FROM calendar_event_locations ORDER BY name") or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $choices['calendar_event_locations'][] = $row;
    }

    return $choices;
}

/**
 * What the edit screen does with an instance's Remaining Spots on save.
 * A reservation takes spots off relatively while the screen is open, so the
 * field is written only when the operator changed it, and only over the
 * value the screen opened with.
 *
 * @param mixed $posted  the posted number_of_remaining_spots
 * @param mixed $was     number_of_remaining_spots_was (value on opening)
 * @param mixed $stored  the instance's spots now (row, or initial spots)
 * @return string 'keep' (leave the row alone), 'write', or 'stale' (refuse)
 */
function pg_calendar_event_spots_decision($posted, $was, $stored)
{
    $was = trim((string) $was);

    // A form opened before the field existed writes as before.
    if (!is_numeric($was)) {
        return 'write';
    }

    $posted = trim((string) $posted);

    if (($posted === $was) || (is_numeric($posted) && ((int) $posted === (int) $was))) {
        return 'keep';
    }

    return ((int) $stored === (int) $was) ? 'write' : 'stale';
}

/**
 * An instance's remaining spots as stored now: its remaining_reservation_spots
 * row, or the event's stored initial spots when it has no row yet (the
 * first reservation creates it from them).
 *
 * @param int $calendar_event_id
 * @param int $recurrence_number
 * @return int
 */
function pg_calendar_event_stored_spots($calendar_event_id, $recurrence_number)
{
    $result = db_q(
        "SELECT number_of_remaining_spots
        FROM remaining_reservation_spots
        WHERE
            (calendar_event_id = ?)
            AND (recurrence_number = ?)",
        array((string) (int) $calendar_event_id, (string) (int) $recurrence_number)) or output_error('Query failed.');

    if ($row = mysqli_fetch_assoc($result)) {
        return (int) $row['number_of_remaining_spots'];
    }

    return (int) db_value("SELECT number_of_initial_spots FROM calendar_events WHERE id = '" . (int) $calendar_event_id . "'");
}

/**
 * The form's two columns: the form column (main information, schedule,
 * locations, reservations) and the status column (publishing and the
 * calendars), then the script that sets the repeat rows. The screens print
 * the form tag, their hidden fields, the row and the save bar around it.
 *
 * The edit screen passes the event's id, which changes four things: no
 * default start and end time, a remaining spots field (whose presence the
 * reservation script reads), the initial spots field hidden until the
 * script shows it, and the stored product kept in the product picker.
 *
 * @param liveform $liveform
 * @param array    $user                     The signed-in user (validate_user())
 * @param array    $calendars                pg_calendar_event_form_choices()['calendars']
 * @param array    $calendar_event_locations pg_calendar_event_form_choices()['calendar_event_locations']
 * @param int      $calendar_event_id        The event being edited, 0 on the create screen
 * @return string  HTML
 */
function pg_calendar_event_form_cards($liveform, $user, $calendars, $calendar_event_locations, $calendar_event_id = 0)
{
    $edit = ($calendar_event_id > 0);

    // The repeat rows a stored or posted repeat type needs start visible.
    $output_recurrence_days_of_the_week_row_style = ' style="display: none"';
    $output_recurrence_month_type_row_style = ' style="display: none"';

    if ($liveform->get_field_value('recurrence') == 1) {
        switch ($liveform->get_field_value('recurrence_type')) {
            case 'day':
                $output_recurrence_days_of_the_week_row_style = '';
                break;

            case 'month':
                $output_recurrence_month_type_row_style = '';
                break;
        }
    }

    $output_calendars = '';

    // Only the calendars the user may post to are offered.
    foreach ($calendars as $calendar) {
        if (validate_calendar_access($calendar['id']) == true) {
            $output_calendars .= '<div class="form-field">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'calendar_' . $calendar['id'], 'id'=>'calendar_' . $calendar['id'], 'value'=>'1', 'class'=>'check multiselect-checkbox')) . '<label class="" for="calendar_' . $calendar['id'] . '">' . h($calendar['name']) . '</label></div>';
        }
    }

    $output_publish_rows = '';

    if (($user['role'] < 3) || ($user['publish_calendar_events'] == 'yes')) {
        // A stored 0 means never unpublish, shown as an empty field.
        if ($edit && !$liveform->get('unpublish_days')) {
            $liveform->set('unpublish_days', '');
        }

        $output_publish_rows =
            '<div class="col-12 mb-3">
                <div class="form-field"><div class="switch">' .
                    $liveform->output_field(array(
                        'type' => 'checkbox',
                        'name' => 'published',
                        'id' => 'published',
                        'value' => '1',
                        'checked' => '',
                        'class' => 'collapse-switcher no-bs',
                        'data-bs-target'=>'#unpublish_days_row')) . '</div>
                        <label class="" for="published">' . lang('Publish to Calendar Pages') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-3" id="unpublish_days_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1">
                                <label for="unpublish_days" class="form-label">' . lang('Unpublish') . '</label>
                                <div class="input-group">
                                    ' . $liveform->output_field(array(
                                        'type' => 'text',
                                        'id' => 'unpublish_days',
                                        'name' => 'unpublish_days',
                                        'class' => 'form-control text-end',
                                        'maxlength' => '5',
                                        'inputmode'=>'numeric',
                                        'data-inputmask-alias'=>'integer',
                                        'data-inputmask-allowminus'=>'false')) . '
                                    <span class="input-group-text" title="' . lang('day(s) after event ends') . '">' . lang('day(s)') . '</span>
                                </div>
                                <div class="form-text">' . lang('leave blank to never unpublish automatically') . '</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
    }

    $start_time_attributes = array('type'=>'text', 'id'=>'start_time', 'name'=>'start_time', 'class'=>'form-control', 'maxlength'=>'19', 'data-pg-datetime' => '');
    $end_time_attributes = array('type'=>'text', 'id'=>'end_time', 'name'=>'end_time', 'class'=>'form-control', 'maxlength'=>'19', 'data-pg-datetime' => '');

    // A new event starts at the next half hour and lasts one hour.
    if (!$edit) {
        $current_time = time();
        $current_minute = date('i', $current_time);

        if ($current_minute < 30) {
            $number_of_minutes_to_add = 30 - $current_minute;
        } else {
            $number_of_minutes_to_add = 60 - $current_minute;
        }

        $default_start_time = $current_time + ($number_of_minutes_to_add * 60);
        $default_end_time = $default_start_time + 3600;

        if (DATE_FORMAT == 'month_day') {
            $month_and_day_format = 'n/j';
        } else {
            $month_and_day_format = 'j/n';
        }

        $start_time_attributes['value'] = date($month_and_day_format . '/Y ' . pg_time_format_code(), $default_start_time);
        $end_time_attributes['value'] = date($month_and_day_format . '/Y ' . pg_time_format_code(), $default_end_time);
    }

    $recurrence_type_options =
        array(
            lang('Day(s)') => 'day',
            lang('Week(s)') => 'week',
            lang('Month(s)') => 'month',
            lang('Year(s)') => 'year'
        );

    if (count($calendar_event_locations) >= 1) {
        $output_calendar_event_locations = '';

        foreach ($calendar_event_locations as $calendar_event_location) {
            $output_calendar_event_locations .= '<div class="form-field">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'calendar_event_location_' . $calendar_event_location['id'], 'id'=>'calendar_event_location_' . $calendar_event_location['id'], 'value'=>'1', 'class'=>'check multiselect-checkbox')) . '<label class="" for="calendar_event_location_' . $calendar_event_location['id'] . '">' . h($calendar_event_location['name']) . '</label></div>';
        }

        $output_locations = '
            <div class="col-12 my-3">
                <h3 class="h5" id="multiselect-checkbox-checker-0_title">' . lang('Available Event Location(s)') . '</h3>
                <div class="card multiselect-checkbox-container rounded-0 mb-1">
                    <div class="card-header border-0 bg-reset">
                        <div class="form-field">
                            <div class="switch"><input id="multiselect-checkbox-checker-0" class="multiselect-checkbox-checker" type="checkbox" aria-labelledby="multiselect-checkbox-checker-0_label multiselect-checkbox-checker-0_title"></div>
                            <label for="multiselect-checkbox-checker-0" class="" id="multiselect-checkbox-checker-0_label">' . lang('Select All') . '</label>
                        </div>
                    </div>
                    <div class="card-body overflow-auto" style="max-height:300px">
                            ' . $output_calendar_event_locations . '
                    </div>
                </div>
            </div>
            <div class="col-12 md:col-6 my-1">
                <label for="location" class="form-label">' . lang('Or enter special Location') . '</label>
                ' . $liveform->output_field(array('type'=>'text', 'name'=>'location', 'id'=>'location', 'class'=>'form-control', 'maxlength'=>'100')) . '
            </div>';
    } else {
        $output_locations = '
        <div class="col-12 md:col-6 my-1">
            <label for="location" class="form-label">' . lang('Enter special Location') . '</label>
            ' . $liveform->output_field(array('type'=>'text', 'name'=>'location', 'id'=>'location', 'class'=>'form-control', 'maxlength'=>'100')) . '
        </div>';
    }

    $separate_reservations_attributes = array('type' => 'checkbox', 'id' => 'separate_reservations', 'name' => 'separate_reservations', 'value' => '1', 'class' => 'no-bs');
    $number_of_initial_spots_row_display = 'block';
    $output_number_of_remaining_spots_row = '';
    $product_id = 0;

    // On the edit screen the initial spots only matter for separately
    // managed repeating instances, so toggle_calendar_event_recurrence()
    // shows them then; the remaining spots of this instance are editable.
    if ($edit) {
        $separate_reservations_attributes['onclick'] = 'toggle_calendar_event_recurrence()';
        $number_of_initial_spots_row_display = 'none';
        $output_number_of_remaining_spots_row = '
                                                                        <div class="col-12 sm:col-auto my-3" id="number_of_remaining_spots_row" style="display:block;">
                                                                            <label class="form-label" for="number_of_remaining_spots">' . lang('Remaining Spots') . '</label>
                                                                            ' . $liveform->output_field(array('type'=>'text', 'id'=>'number_of_remaining_spots', 'name'=>'number_of_remaining_spots', 'size'=>'3', 'maxlength'=>'9', 'class' => 'form-control text-end', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'integer', 'data-inputmask-allowminus'=>'false')) . '
                                                                        </div> ';
        $product_id = $liveform->get_field_value('product_id');
    }

    // The form column of the edit screen layout (ui/edit-screen-wide-layout.md)
    // holds the four field cards; the calendars the event is posted to and
    // whether it is published sit in a sticky status column on the right.
    // Below lg the status column follows the form column. The screens put
    // both in their row, before the save bar.
    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Main Informations') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Event'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="short_description" class="form-label">' . lang('Short Description') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'short_description', 'id'=>'short_description', 'class'=>'form-control', 'maxlength'=>'255')) . '
                                        </div>
                                        <div class="col-12 my-5">
                                            <label for="full_description" class="form-label">' . lang('Full Description') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'full_description', 'id'=>'full_description', 'class'=>'form-control')) . '
                                        </div>
                                        <div class="col-12 my-5">
                                            <label for="notes" class="form-label">' . lang('Special Event Notes for display on any Calendar Event Pages') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'notes', 'id'=>'notes', 'class'=>'form-control')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Event Schedule') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-1">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'id' => 'all_day', 'name'=>'all_day', 'value'=>'1', 'class'=>'no-bs', 'onclick' => 'toggle_calendar_event_all_day()')) . '</div>
                                                <label class="" for="all_day">' . lang('All Day Event') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <div class="border-1 border p-3 my-3 rounded-4">
                                                <label for="start_time" class="form-label">' . lang('Start Date') . '<span id="start_time_suffix"> ' . lang('& Time') . '</span><span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>
                                                ' . $liveform->output_field($start_time_attributes) . '
                                                <span id="show_start_time_container">
                                                    <div class="form-field ms-1 mt-3">
                                                        <div class="switch">' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'show_start_time', 'name' => 'show_start_time', 'value' => '1', 'class' => 'no-bs')) . '</div>
                                                        <label class="" for="show_start_time">' . lang('Show Start Time') . '</label>
                                                    </div>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <div class="border-1 border p-3 my-3 rounded-4">
                                                <label for="end_time" class="form-label">' . lang('End Date') . '<span id="end_time_suffix"> ' . lang('& Time') . '</span><span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>
                                                ' . $liveform->output_field($end_time_attributes) . '
                                                <span id="show_end_time_container">
                                                    <div class="form-field ms-1 mt-3">
                                                        <div class="switch">' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'show_end_time', 'name' => 'show_end_time', 'value' => '1', 'class' => 'no-bs')) . '</div>
                                                        <label class="" for="show_end_time">' . lang('Show End Time') . '</label>
                                                    </div>
                                                </span>
                                            </div>
                                        </div>
                                        ' . pg_fields_script() . '
                                        <script>toggle_calendar_event_all_day()</script>
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'recurrence', 'name' => 'recurrence', 'value' => '1', 'class' => 'collapse-switcher no-bs', 'onclick' => 'toggle_calendar_event_recurrence()', 'data-bs-target'=>'#show_or_hide_calendar_event_recurrence_row')) . '</div>
                                                <label class="" for="recurrence">' . lang('Repeat') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-3" id="show_or_hide_calendar_event_recurrence_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-auto my-1">
                                                            <div class="input-group">
                                                                <div class="input-group-text">' . lang('Up To') . ':</div>
                                                                ' . $liveform->output_field(array('type'=>'text', 'id'=>'total_recurrence_number', 'name'=>'total_recurrence_number', 'aria-label'=>lang('Up To'), 'size'=>'5', 'maxlength'=>'9', 'class' => 'form-control text-end', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'integer', 'data-inputmask-allowminus'=>'false')) . ' 
                                                                ' . $liveform->output_field(array('type'=>'select', 'id'=>'recurrence_type', 'name'=>'recurrence_type', 'aria-label'=>lang('Repeat'), 'class' => 'form-control', 'options'=>$recurrence_type_options, 'onchange'=>'change_calendar_event_recurrence_type()')) . '
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-3" id="recurrence_days_of_the_week_row"' . $output_recurrence_days_of_the_week_row_style . '>
                                                            <fieldset>
                                                                <legend class="form-label fs-md">' . lang('On') . '</legend>
                                                            <div class="row row-cols-1 sm:row-cols-2 md:row-cols-3 lg:row-cols-auto">
                                                                <div class="col">
                                                                    <div class="form-field">
                                                                    ' . $liveform->output_field(array(
                                                                        'type' => 'checkbox',
                                                                        'id' => 'recurrence_day_mon',
                                                                        'name' => 'recurrence_day_mon',
                                                                        'value' => '1',
                                                                        'class' => 'check')) . '
                                                                        <label class="" for="recurrence_day_mon">' . lang('Monday') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col">
                                                                    <div class="form-field">
                                                                    ' . $liveform->output_field(array(
                                                                        'type' => 'checkbox',
                                                                        'id' => 'recurrence_day_tue',
                                                                        'name' => 'recurrence_day_tue',
                                                                        'value' => '1',
                                                                        'class' => 'check')) . '
                                                                        <label class="" for="recurrence_day_tue">' . lang('Tuesday') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col">
                                                                    <div class="form-field">
                                                                    ' . $liveform->output_field(array(
                                                                        'type' => 'checkbox',
                                                                        'id' => 'recurrence_day_wed',
                                                                        'name' => 'recurrence_day_wed',
                                                                        'value' => '1',
                                                                        'class' => 'check')) . '
                                                                        <label class="" for="recurrence_day_wed">' . lang('Wednesday') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col">
                                                                    <div class="form-field">
                                                                    ' . $liveform->output_field(array(
                                                                        'type' => 'checkbox',
                                                                        'id' => 'recurrence_day_thu',
                                                                        'name' => 'recurrence_day_thu',
                                                                        'value' => '1',
                                                                        'class' => 'check')) . '
                                                                        <label class="" for="recurrence_day_thu">' . lang('Thursday') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col">
                                                                    <div class="form-field">
                                                                    ' . $liveform->output_field(array(
                                                                        'type' => 'checkbox',
                                                                        'id' => 'recurrence_day_fri',
                                                                        'name' => 'recurrence_day_fri',
                                                                        'value' => '1',
                                                                        'class' => 'check')) . '
                                                                        <label class="" for="recurrence_day_fri">' . lang('Friday') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col">
                                                                    <div class="form-field">
                                                                    ' . $liveform->output_field(array(
                                                                        'type' => 'checkbox',
                                                                        'id' => 'recurrence_day_sat',
                                                                        'name' => 'recurrence_day_sat',
                                                                        'value' => '1',
                                                                        'class' => 'check')) . '
                                                                        <label class="" for="recurrence_day_sat">' . lang('Saturday') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col">
                                                                    <div class="form-field">
                                                                        ' . $liveform->output_field(array(
                                                                            'type' => 'checkbox',
                                                                            'id' => 'recurrence_day_sun',
                                                                            'name' => 'recurrence_day_sun',
                                                                            'value' => '1',
                                                                            'class' => 'check')) . '
                                                                        <label class="" for="recurrence_day_sun">' . lang('Sunday') . '</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            </fieldset>
                                                        </div>
                                                        <div class="col-12 my-5" id="recurrence_month_type_row"' . $output_recurrence_month_type_row_style . '>
                                                            <fieldset>
                                                                <legend class="form-label fs-md">' . lang('By') . '</legend>
                                                            <div class="form-field">
                                                                ' . $liveform->output_field(array('type' => 'radio', 'id' => 'recurrence_month_type_day_of_the_month', 'name' => 'recurrence_month_type', 'value' => 'day_of_the_month', 'checked' => 'checked', 'class' => 'radio')) . '
                                                                <label class="" for="recurrence_month_type_day_of_the_month">' . lang('Day of the Month (e.g. 15th)') . '</label> 
                                                            </div>
                                                            <div class="form-field">
                                                                ' . $liveform->output_field(array('type' => 'radio', 'id' => 'recurrence_month_type_day_of_the_week', 'name' => 'recurrence_month_type', 'value' => 'day_of_the_week', 'class' => 'radio')) . '
                                                                <label class="" for="recurrence_month_type_day_of_the_week">' . lang('Day of the Week (e.g. second Sunday)') . '</label>
                                                            </div>
                                                            </fieldset>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Event Location(s)') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        ' . $output_locations . '
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Reservations') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'reservations', 'name' => 'reservations', 'value' => '1', 'class' => 'collapse-switcher no-bs', 'onclick' => 'toggle_calendar_event_recurrence()', 'data-bs-target'=>'#show_or_hide_reservations_row')) . '</div>
                                                <label class="" for="reservations">' . lang('Accept Reservations') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-3" id="show_or_hide_reservations_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-3" id="separate_reservations_row" style="display: none">
                                                            <div class="form-field">
                                                                <div class="switch">' . $liveform->output_field($separate_reservations_attributes) . '</div>
                                                                <label class="" for="separate_reservations">' . lang('Manage Reservations Separately for each Repeating Instance') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-3">
                                                            <div class="form-field">
                                                                <div class="switch">' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'limit_reservations', 'name' => 'limit_reservations', 'value' => '1', 'class' => 'collapse-switcher no-bs', 'data-bs-target'=>'#show_or_hide_limit_reservations')) . '</div>
                                                                <label class="" for="limit_reservations">' . lang('Limit Reservations') . '</label>
                                                            </div>
                                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-3" id="show_or_hide_limit_reservations">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 sm:col-auto my-3" id="number_of_initial_spots_row" style="display:' . $number_of_initial_spots_row_display . ';">
                                                                            <label class="form-label" for="number_of_initial_spots">' . lang('Number Of Initial Spots') . '</label>
                                                                            ' . $liveform->output_field(array('type'=>'text', 'id'=>'number_of_initial_spots', 'name'=>'number_of_initial_spots', 'size'=>'3', 'maxlength'=>'9', 'class' => 'form-control text-end', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'integer', 'data-inputmask-allowminus'=>'false')) . '
                                                                        </div> ' . $output_number_of_remaining_spots_row . '
                                                                        <div class="col-12 my-3">
                                                                            <label class="form-label" for="no_remaining_spots_message">' . lang('No Longer Available Message') . '</label>
                                                                            ' . $liveform->output_field(array('type'=>'textarea', 'id'=>'no_remaining_spots_message', 'name'=>'no_remaining_spots_message', 'class'=>'form-control')) . '
                                                                        </div> 
                                                                    </div> 
                                                                </div> 
                                                            </div> 
                                                        </div> 
                                                        <div class="col-12 md:col-6 xl:col-4 my-3">
                                                            <label for="reserve_button_label" class="form-label">' . lang('Reserve Button Label') . '</label>
                                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'reserve_button_label', 'id'=>'reserve_button_label', 'class'=>'form-control', 'maxlength'=>'50')) . '
                                                        </div>
                                                        <div class="col-12 md:col-6 xl:col-4 my-3">
                                                            <label for="product_id" class="form-label">' . lang('Reservation Product') . '</label>
                                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'product_id', 'id'=>'product_id', 'class'=>'form-control', 'options'=>get_product_options(true, $product_id))) . '
                                                        </div>
                                                        <div class="col-12 md:col-6 xl:col-4 my-3">
                                                            <label for="next_page_id" class="form-label">' . lang('Next Page') . '</label>
                                                            ' . pg_picker_field('page', 'next_page_id', $liveform->get_field_value('next_page_id')) . pg_picker_script() . '
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Event Calendar(s)') . '
                                    </h2>
                                    <div class="card-body">
                                        <div class="row">
                                            ' . $output_publish_rows . '
                                            <div class="col-12 my-3">
                                                <h3 class="h5" id="multiselect-checkbox-checker-1_title">' . lang('Post to any of my Calendars') . '</h3>
                                                <div class="card multiselect-checkbox-container rounded-0 mb-1">
                                                    <div class="card-header border-0 bg-reset">
                                                        <div class="form-field">
                                                            <div class="switch"><input id="multiselect-checkbox-checker-1" class="multiselect-checkbox-checker" type="checkbox" aria-labelledby="multiselect-checkbox-checker-1_label multiselect-checkbox-checker-1_title"></div>
                                                            <label for="multiselect-checkbox-checker-1" class="" id="multiselect-checkbox-checker-1_label">' . lang('Select All') . '</label>
                                                        </div>
                                                    </div>
                                                    <div class="card-body overflow-auto" style="max-height:300px">
                                                        ' . $output_calendars . '
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <script>toggle_calendar_event_recurrence();</script>
';
}
