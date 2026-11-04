<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the calendar event form shared by add_calendar_event.php and
 * edit_calendar_event.php (includes/forms/calendar_event_form.php): the start
 * date of every repeating instance that the location check walks through, and
 * that both screens check a submitted event through the one validator.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');

function calendar_event_form_test_days($chosen)
{
	$days = array();

	foreach (array('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat') as $day) {
		$days[$day] = in_array($day, $chosen) ? '1' : '0';
	}

	return $days;
}

function test_calendar_event_form_no_repeat_is_the_event_alone()
{
	pg_assert_same(array(0 => '2026-10-27'), pg_calendar_event_recurrence_dates('2026-10-27', '0', 'day', calendar_event_form_test_days(array()), ''));
}

function test_calendar_event_form_daily_repeat_runs_on_the_chosen_weekdays_only()
{
	// 2026-10-26 is a Monday.
	pg_assert_same(
		array(0 => '2026-10-26', 1 => '2026-10-28', 2 => '2026-11-02', 3 => '2026-11-04'),
		pg_calendar_event_recurrence_dates('2026-10-26', '3', 'day', calendar_event_form_test_days(array('mon', 'wed')), ''));
}

function test_calendar_event_form_daily_repeat_without_a_weekday_stops_at_the_event()
{
	pg_assert_same(array(0 => '2026-10-26'), pg_calendar_event_recurrence_dates('2026-10-26', '2', 'day', calendar_event_form_test_days(array()), ''));
}

function test_calendar_event_form_weekly_repeat_adds_seven_days()
{
	pg_assert_same(
		array(0 => '2026-12-24', 1 => '2026-12-31', 2 => '2027-01-07'),
		pg_calendar_event_recurrence_dates('2026-12-24', '2', 'week', calendar_event_form_test_days(array()), ''));
}

function test_calendar_event_form_monthly_day_of_the_month_falls_back_to_the_last_day()
{
	pg_assert_same(
		array(0 => '2026-01-31', 1 => '2026-02-28', 2 => '2026-03-31'),
		pg_calendar_event_recurrence_dates('2026-01-31', '2', 'month', calendar_event_form_test_days(array()), 'day_of_the_month'));
}

function test_calendar_event_form_monthly_day_of_the_week_keeps_the_week()
{
	// The fourth Tuesday of October 2026, then of November and December.
	pg_assert_same(
		array(0 => '2026-10-27', 1 => '2026-11-24', 2 => '2026-12-22'),
		pg_calendar_event_recurrence_dates('2026-10-27', '2', 'month', calendar_event_form_test_days(array()), 'day_of_the_week'));
}

function test_calendar_event_form_monthly_fifth_week_is_read_as_the_last()
{
	// The fifth Thursday of October 2026 repeats on the last Thursday, which
	// in December is the last day of the month.
	pg_assert_same(
		array(0 => '2026-10-29', 1 => '2026-11-26', 2 => '2026-12-31'),
		pg_calendar_event_recurrence_dates('2026-10-29', '2', 'month', calendar_event_form_test_days(array()), 'day_of_the_week'));
}

function test_calendar_event_form_yearly_repeat_moves_a_leap_day_to_the_month_end()
{
	pg_assert_same(
		array(0 => '2024-02-29', 1 => '2025-02-28', 2 => '2026-02-28'),
		pg_calendar_event_recurrence_dates('2024-02-29', '2', 'year', calendar_event_form_test_days(array()), ''));
}

function test_calendar_event_form_both_screens_validate_through_the_shared_form()
{
	foreach (array('add_calendar_event.php' => "pg_calendar_event_form_validate(\$liveform)", 'edit_calendar_event.php' => "pg_calendar_event_form_validate(\$liveform, \$liveform->get_field_value('id'))") as $file => $call) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true(strpos($source, "require_once(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');") !== false, $file . ' does not load the shared form');
		pg_assert_true(strpos($source, $call) !== false, $file . ' does not validate through the shared form');
		pg_assert_false(strpos($source, 'check_calendar_event_location_availability(') !== false, $file . ' still checks locations on its own');
	}
}

function test_calendar_event_form_select_all_switches_name_their_list()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');

	foreach (array('0' => 'Available Event Location(s)', '1' => 'Post to any of my Calendars') as $n => $heading) {
		$id = 'multiselect-checkbox-checker-' . $n;

		pg_assert_contains('<h3 class="h5" id="' . $id . '_title">\' . lang(\'' . $heading . '\') . \'</h3>', $source, $heading . ' heading has an id');
		pg_assert_contains('<input id="' . $id . '" class="multiselect-checkbox-checker" type="checkbox" aria-labelledby="' . $id . '_label ' . $id . '_title">', $source, $heading . ' Select All reads its list');
		pg_assert_contains('<label for="' . $id . '" class="" id="' . $id . '_label">', $source, $heading . ' Select All label has an id');
	}
}

function test_calendar_event_form_start_calendars_tick_the_requested_offered_calendar()
{
	pg_assert_same(array('7'), pg_calendar_event_form_start_calendars(array('3', '7', '9'), '7'));
	pg_assert_same(array('7'), pg_calendar_event_form_start_calendars(array(3, 7, 9), '7'));
}

function test_calendar_event_form_start_calendars_tick_the_only_offered_calendar()
{
	pg_assert_same(array('4'), pg_calendar_event_form_start_calendars(array('4'), ''));
	pg_assert_same(array('4'), pg_calendar_event_form_start_calendars(array('4'), '99'));
}

function test_calendar_event_form_start_calendars_tick_nothing_without_a_clear_choice()
{
	pg_assert_same(array(), pg_calendar_event_form_start_calendars(array('3', '7'), ''));
	pg_assert_same(array(), pg_calendar_event_form_start_calendars(array('3', '7'), '99'));
	pg_assert_same(array(), pg_calendar_event_form_start_calendars(array(), '7'));
	pg_assert_same(array(), pg_calendar_event_form_start_calendars(array('3', '7'), null));
}

function test_calendar_event_form_create_link_carries_the_viewed_calendar()
{
	$calendars = file_get_contents(PG_FUNCTIONS_DIR . '/calendars.php');
	pg_assert_true(strpos($calendars, "add_calendar_event.php?calendar_id=' . urlencode(") !== false, 'calendars.php does not pass the viewed calendar to the create screen');

	$add = file_get_contents(PG_FUNCTIONS_DIR . '/add_calendar_event.php');
	pg_assert_true(strpos($add, 'pg_calendar_event_form_start_calendars(') !== false, 'add_calendar_event.php does not tick the start calendar');
}

function test_calendar_event_form_uses_the_edit_screen_layout()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');

	pg_assert_contains('<div class="col-12 lg:col-8 2xl:col-9 order-1">', $source, 'the field cards sit in the form column');
	pg_assert_contains('<div class="col-12 lg:col-4 2xl:col-3 order-2">', $source, 'the calendars card sits in the status column');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status column box sticks below the top bar');
	pg_assert_true(strpos($source, '<div class="card my-7">') === false, 'no card is stacked full width');

	foreach (array('add_calendar_event.php', 'edit_calendar_event.php') as $file) {
		$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">', $screen, $file . ' puts its save bar in its own sticky column');
	}
}

function test_calendar_event_form_check_window_reads_the_day_month_order()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	if (DATE_FORMAT !== 'day_month') {
		return;
	}

	// strtotime() read '15/12/2026 10:00' as false and '05/12/2026' as 12 May.
	pg_assert_same(array('start' => '2026-12-15 10:00', 'end' => '2026-12-15 12:30'), pg_calendar_event_form_check_window('15/12/2026 10:00', '15/12/2026 12:30'));
	pg_assert_same(array('start' => '2026-12-05 10:00', 'end' => '2026-12-05 12:30'), pg_calendar_event_form_check_window('05/12/2026 10:00 AM', '05/12/2026 12:30 PM'));

	// An all day event carries 12:00 AM and 11:59 PM.
	pg_assert_same(array('start' => '2026-12-15 00:00', 'end' => '2026-12-16 23:59'), pg_calendar_event_form_check_window('15/12/2026 12:00 AM', '16/12/2026 11:59 PM'));
}

function test_calendar_event_form_non_repeating_location_check_uses_the_converted_window()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');

	pg_assert_contains("\$check_window['start'], \$check_window['end'], \$calendar_event_id);", $source, 'the non-repeating check is given Y-m-d times');
	pg_assert_true(strpos($source, '$calendar_event_location, $start_time, $end_time, $calendar_event_id') === false, 'the raw form value no longer reaches the check');
}

function test_calendar_event_form_whole_numbers_refuse_signs_decimals_and_overflow()
{
	pg_assert_true(pg_calendar_event_whole_number_ok('3', 1, 999999999));
	pg_assert_true(pg_calendar_event_whole_number_ok(' 65535 ', 0, 65535));
	pg_assert_true(pg_calendar_event_whole_number_ok('0', 0, 65535));
	pg_assert_false(pg_calendar_event_whole_number_ok('0', 1, 999999999), 'a repeat count starts at 1');
	pg_assert_false(pg_calendar_event_whole_number_ok('2.5', 1, 999999999), '2.5 was stored as 3');
	pg_assert_false(pg_calendar_event_whole_number_ok('2,5', 1, 999999999));
	pg_assert_false(pg_calendar_event_whole_number_ok('-12', 0, 999999999), '-12 was stored as 0');
	pg_assert_false(pg_calendar_event_whole_number_ok('abc', 0, 999999999));
	pg_assert_false(pg_calendar_event_whole_number_ok('', 0, 999999999));
	pg_assert_false(pg_calendar_event_whole_number_ok('99999', 0, 65535), '99999 unpublish days was stored as 65535');
	pg_assert_false(pg_calendar_event_whole_number_ok('99999999999999999999', 0, 999999999));
	pg_assert_false(pg_calendar_event_whole_number_ok(array('1'), 0, 9));
}

function test_calendar_event_form_number_fields_take_no_minus_or_decimals()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');

	pg_assert_true(strpos($source, "'data-inputmask-alias'=>'decimal'") === false, 'no decimal mask on a count');
	pg_assert_same(4, substr_count($source, "'data-inputmask-allowminus'=>'false'"), 'repeat, unpublish and both spots fields take no minus');
}

function test_calendar_event_form_time_suffix_has_its_own_id()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	// pg_fields.js names a label without an id <field>_label: the suffix
	// span with that id made the all day switch hide the whole label.
	pg_assert_true(strpos($source, 'id="start_time_label"') === false && strpos($source, 'id="end_time_label"') === false);
	pg_assert_contains('<span id="start_time_suffix">', $source);
	pg_assert_contains("getElementById('start_time_suffix')", $script);
	pg_assert_true(strpos($script, "getElementById('start_time_label')") === false);
}

function test_calendar_event_form_reservations_switch_reruns_the_repeat_toggle()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');

	pg_assert_contains("'id' => 'reservations', 'name' => 'reservations', 'value' => '1', 'class' => 'collapse-switcher no-bs', 'onclick' => 'toggle_calendar_event_recurrence()'", $source, 'Repeat then Reservations shows the separate reservations row');
}

function test_calendar_event_edit_screen_clears_the_saved_form_before_send_to()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_calendar_event.php');
	$clear = strpos($source, "\$send_to = \$liveform->get_field_value('send_to');\n        \$liveform->remove_form();");

	pg_assert_true($clear !== false, 'the form leaves the session on save');
	pg_assert_true($clear < strpos($source, 'pg_safe_redirect_path($send_to)'), 'before the send_to redirect');
}

function test_calendar_text_too_long_counts_characters_not_bytes()
{
	pg_assert_false(pg_calendar_text_too_long(str_repeat('ş', 100), 100), '100 Turkish letters fit a 100 character column');
	pg_assert_true(pg_calendar_text_too_long(str_repeat('a', 101), 100), 'one character too many');
	pg_assert_false(pg_calendar_text_too_long('', 100));
	pg_assert_false(pg_calendar_text_too_long(null, 100), 'a field that was not posted');
	pg_assert_true(pg_calendar_text_too_long(array('x'), 100), 'an array is never a text');
}

function test_calendar_forms_refuse_texts_longer_than_their_columns()
{
	$event = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');

	pg_assert_contains("'name' => array(lang('Name'), 100)", $event);
	pg_assert_contains("'short_description' => array(lang('Short Description'), 255)", $event);
	pg_assert_contains("'reserve_button_label' => array(lang('Reserve Button Label'), 50)", $event);

	foreach (array('includes/forms/calendar_form.php', 'add_calendar_event_location.php', 'edit_calendar_event_location.php') as $file) {
		pg_assert_contains("pg_calendar_form_check_lengths(\$liveform, array('name' => array(lang('Name'), 100)));", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file);
	}
}

function test_calendar_event_form_accepts_only_the_repeat_types_it_expands()
{
	$event = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');

	// An unknown type was stored as '' and drew the event on its start day
	// once per repeat.
	pg_assert_contains("array('day', 'week', 'month', 'year'), true)", $event);
	pg_assert_contains("array('day_of_the_month', 'day_of_the_week'), true)", $event);
}

function test_calendar_event_all_day_off_restores_a_full_date_and_time()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	// Switching all day off left the date-only text in the store, and the
	// save was refused while the box showed a date and time.
	pg_assert_contains("field.setAttribute('data-calendar-time', value.slice(11));", $script);
	pg_assert_contains("pgDateTimeField.set(field, date + ' ' + (field.getAttribute('data-calendar-time') || pair[1]));", $script);
}
