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
 * The form's five cards (main information, schedule, locations, calendars,
 * reservations), from the opening row to the script that sets the repeat
 * rows. The screens print the form tag, their hidden fields and buttons
 * around it.
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
            '<div class="col-12 my-5">
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
                                        'data-inputmask-alias'=>'decimal')) . '
                                    <span class="input-group-text" title="' . lang('day(s) after event ends') . '">' . lang('day(s)') . '</span>
                                </div>
                                <div class="form-text text-end">' . lang('leave blank to never unpublish automatically') . '</div>
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

        $start_time_attributes['value'] = date($month_and_day_format . '/Y g:i A', $default_start_time);
        $end_time_attributes['value'] = date($month_and_day_format . '/Y g:i A', $default_end_time);
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
                <h5>' . lang('Available Event Location(s)') . '</h5>
                <div class="card multiselect-checkbox-container rounded-0 mb-1">
                    <div class="card-header border-0 bg-reset">
                        <div class="form-field">
                            <div class="switch"><input id="multiselect-checkbox-checker-0" class="multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox"></div>
                            <label for="multiselect-checkbox-checker-0" class="">' . lang('Select All') . '</label>
                        </div>
                    </div>
                    <div class="card-body overflow-auto" style="max-height:300px">
                            ' . $output_calendar_event_locations . '
                    </div>
                </div>
            </div>
            <div class="col-12 sm:col-auto my-1">
                <label for="location" class="form-label">' . lang('Or enter special Location') . '</label>
                ' . $liveform->output_field(array('type'=>'text', 'name'=>'location', 'id'=>'location', 'class'=>'form-control', 'maxlength'=>'100')) . '
            </div>';
    } else {
        $output_locations = '
        <div class="col-12 sm:col-auto my-1">
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
                                                                            ' . $liveform->output_field(array('type'=>'text', 'id'=>'number_of_remaining_spots', 'name'=>'number_of_remaining_spots', 'size'=>'3', 'maxlength'=>'9', 'class' => 'form-control text-end', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'decimal')) . '
                                                                        </div> ';
        $product_id = $liveform->get_field_value('product_id');
    }

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Event'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-8 my-3">
                                            <label for="short_description" class="form-label">' . lang('Short Description') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'short_description', 'id'=>'short_description', 'class'=>'form-control', 'maxlength'=>'255')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
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
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Event Schedule') . '
                                </div>
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
                                                <label for="start_time" class="form-label">' . lang('Start Date') . '<span id="start_time_label"> ' . lang('& Time') . '</span></label>
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
                                                <label for="end_time" class="form-label">' . lang('End Date') . '<span id="end_time_label"> ' . lang('& Time') . '</span></label>
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
                                                                ' . $liveform->output_field(array('type'=>'text', 'id'=>'total_recurrence_number', 'name'=>'total_recurrence_number', 'size'=>'5', 'maxlength'=>'9', 'class' => 'form-control text-end', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'decimal')) . ' 
                                                                ' . $liveform->output_field(array('type'=>'select', 'id'=>'recurrence_type', 'name'=>'recurrence_type', 'class' => 'form-control', 'options'=>$recurrence_type_options, 'onchange'=>'change_calendar_event_recurrence_type()')) . '
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-3" id="recurrence_days_of_the_week_row"' . $output_recurrence_days_of_the_week_row_style . '>
                                                            <h5 class="fg-secondary">' . lang('On') . '</h5>
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
                                                        </div>
                                                        <div class="col-12 my-5" id="recurrence_month_type_row"' . $output_recurrence_month_type_row_style . '>
                                                            <h5 class="fg-secondary">' . lang('By') . '</h5>
                                                            <div class="form-field">
                                                                ' . $liveform->output_field(array('type' => 'radio', 'id' => 'recurrence_month_type_day_of_the_month', 'name' => 'recurrence_month_type', 'value' => 'day_of_the_month', 'checked' => 'checked', 'class' => 'radio')) . '
                                                                <label class="" for="recurrence_month_type_day_of_the_month">' . lang('Day of the Month (e.g. 15th)') . '</label> 
                                                            </div>
                                                            <div class="form-field">
                                                                ' . $liveform->output_field(array('type' => 'radio', 'id' => 'recurrence_month_type_day_of_the_week', 'name' => 'recurrence_month_type', 'value' => 'day_of_the_week', 'class' => 'radio')) . '
                                                                <label class="" for="recurrence_month_type_day_of_the_week">' . lang('Day of the Week (e.g. second Sunday)') . '</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Event Location(s)') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        ' . $output_locations . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Event Calendar(s)') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <h5>' . lang('Post to any of my Calendars') . '</h5>
                                            <div class="card multiselect-checkbox-container rounded-0 mb-1">
                                                <div class="card-header border-0 bg-reset">
                                                    <div class="form-field">
                                                        <div class="switch"><input id="multiselect-checkbox-checker-1" class="multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox"></div>
                                                        <label for="multiselect-checkbox-checker-1" class="">' . lang('Select All') . '</label>
                                                    </div>
                                                </div>
                                                <div class="card-body overflow-auto" style="max-height:300px">
                                                    ' . $output_calendars . '
                                                </div>
                                            </div>
                                            ' . $output_publish_rows . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Reservations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'reservations', 'name' => 'reservations', 'value' => '1', 'class' => 'collapse-switcher no-bs', 'data-bs-target'=>'#show_or_hide_reservations_row')) . '</div>
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
                                                                            ' . $liveform->output_field(array('type'=>'text', 'id'=>'number_of_initial_spots', 'name'=>'number_of_initial_spots', 'size'=>'3', 'maxlength'=>'9', 'class' => 'form-control text-end', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'decimal')) . '
                                                                        </div> ' . $output_number_of_remaining_spots_row . '
                                                                        <div class="col-12 my-3">
                                                                            <label class="form-label" for="no_remaining_spots_message">' . lang('No Longer Available Message') . '</label>
                                                                            ' . $liveform->output_field(array('type'=>'textarea', 'id'=>'no_remaining_spots_message', 'name'=>'no_remaining_spots_message', 'class'=>'form-control')) . '
                                                                        </div> 
                                                                    </div> 
                                                                </div> 
                                                            </div> 
                                                        </div> 
                                                        <div class="col-12 md:col-4 my-3">
                                                            <label for="reserve_button_label" class="form-label">' . lang('Reserve Button Label') . '</label>
                                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'reserve_button_label', 'id'=>'reserve_button_label', 'class'=>'form-control', 'maxlength'=>'50')) . '
                                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                        </div>
                                                        <div class="col-12 md:col-4 my-3">
                                                            <label for="product_id" class="form-label">' . lang('Reservation Product') . '</label>
                                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'product_id', 'id'=>'product_id', 'class'=>'form-control', 'options'=>get_product_options(true, $product_id))) . '
                                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                        </div>
                                                        <div class="col-12 md:col-4 my-3">
                                                            <label for="next_page_id" class="form-label">' . lang('Next Page') . '</label>
                                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'next_page_id', 'id'=>'next_page_id', 'class'=>'form-control', 'options'=>get_page_options())) . '
                                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                        </div>
                                                    </div>
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
