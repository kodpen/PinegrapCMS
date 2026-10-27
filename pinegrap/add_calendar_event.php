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

    // Assume that recurrence rows should be hidden until we find out otherwise.
    $output_recurrence_days_of_the_week_row_style = ' style="display: none"';
    $output_recurrence_month_type_row_style = ' style="display: none"';

    // If recurrence is enabled, show recurrence rows.
    if ($liveform->get_field_value('recurrence') == 1) {
       

        // Determine if other fields should be shown depending on the recurrence type.
        switch ($liveform->get_field_value('recurrence_type')) {
            case 'day':
                $output_recurrence_days_of_the_week_row_style = '';
                break;

            case 'month':
                $output_recurrence_month_type_row_style = '';
                break;
        }
    }
    
    // get all calendars for list of calendars
    $query =
        "SELECT
           id,
           name
        FROM calendars
        ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $calendars = array();
    
    // loop through all calendars in order to build array
    while ($row = mysqli_fetch_assoc($result)) {
        $calendars[] = $row;
    }
    
    $output_calendars = '';
    
    // loop through all calendars in order to prepare to output list of calendars
    foreach ($calendars as $calendar) {
        // if user has access to calendar, then include this calendar
        if (validate_calendar_access($calendar['id']) == true) {
            $output_calendars .= '<div class="form-field">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'calendar_' . $calendar['id'], 'id'=>'calendar_' . $calendar['id'], 'value'=>'1', 'class'=>'check multiselect-checkbox')) . '<label class="" for="calendar_' . $calendar['id'] . '">' . h($calendar['name']) . '</label></div>';

        }
    }

    $output_publish_rows = '';
    
    // If the user has access to publish calendar events then output publish rows.
    if (($user['role'] < 3) || ($user['publish_calendar_events'] == 'yes')) {
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
    
    // get current time
    $current_time = time();
    
    // get current minute
    $current_minute = date('i', $current_time);
    
    // get number of minutes to add in order to prepare default start time for next half hour
    if ($current_minute < 30) {
        $number_of_minutes_to_add = 30 - $current_minute;
    } else {
        $number_of_minutes_to_add = 60 - $current_minute;
    }
    
    $number_of_seconds_to_add = $number_of_minutes_to_add * 60;
    
    // default start time is next half hour
    $default_start_time = $current_time + $number_of_seconds_to_add;
    
    // default end time is one hour after default start time
    $default_end_time = $default_start_time + 3600;

    // If the date format is month and then day, then use that format.
    if (DATE_FORMAT == 'month_day') {
        $month_and_day_format = 'n/j';

    // Otherwise the date format is day and then month, so use that format.
    } else {
        $month_and_day_format = 'j/n';
    }
    
    $default_start_date_and_time = date($month_and_day_format . '/Y g:i A', $default_start_time);
    $default_end_date_and_time = date($month_and_day_format . '/Y g:i A', $default_end_time);
    
    // set options for recurrence type pick list
    $recurrence_type_options =
        array(
            lang('Day(s)') => 'day',
            lang('Week(s)') => 'week',
            lang('Month(s)') => 'month',
            lang('Year(s)') => 'year'
        );

    // get all calendar event locations
    $query =
        "SELECT
            id,
            name
        FROM calendar_event_locations
        ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $calendar_event_locations = array();
    
    // loop through all calendar event locations in order to build array
    while ($row = mysqli_fetch_assoc($result)) {
        $calendar_event_locations[] = $row;
    }

    
    // if there is at least one location, then prepare to output locations
    if (count($calendar_event_locations) >= 1) {
        $output_calendar_event_locations = '';
        
        // loop through all calendar event locations in order to prepare to output list of calendar event locations
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
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
    ' . get_date_time_picker_format() . '
    ' . get_wysiwyg_editor_code(array('full_description', 'notes', 'no_remaining_spots_message')) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="add_calendar_event.php" method="post">
                    ' . get_token_field() . '
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
                                                ' . $liveform->output_field(array('type'=>'text', 'id'=>'start_time', 'name'=>'start_time', 'value'=>$default_start_date_and_time, 'class'=>'form-control', 'maxlength'=>'19', 'data-pg-datetime' => '')) . '
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
                                                ' . $liveform->output_field(array('type'=>'text', 'id'=>'end_time', 'name'=>'end_time', 'value'=>$default_end_date_and_time, 'class'=>'form-control', 'maxlength'=>'19', 'data-pg-datetime' => '')) . '
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
                                                                <div class="switch">' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'separate_reservations', 'name' => 'separate_reservations', 'value' => '1', 'class' => 'no-bs')) . '</div>
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
                                                                        <div class="col-12 sm:col-auto my-3" id="number_of_initial_spots_row" style="display:block;">
                                                                            <label class="form-label" for="number_of_initial_spots">' . lang('Number Of Initial Spots') . '</label>
                                                                            ' . $liveform->output_field(array('type'=>'text', 'id'=>'number_of_initial_spots', 'name'=>'number_of_initial_spots', 'size'=>'3', 'maxlength'=>'9', 'class' => 'form-control text-end', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'decimal')) . '
                                                                        </div> 
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
                                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'product_id', 'id'=>'product_id', 'class'=>'form-control', 'options'=>get_product_options())) . '
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