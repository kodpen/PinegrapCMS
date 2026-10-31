<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the recurring instance number on the edit calendar event screen.
 * Opened from the calendars list (no ?recurrence_number=) the screen wrote two
 * "Undefined array key" warnings, printed on the page where errors are shown;
 * ?recurrence_number[]=x and a posted recurrence_number[]=x answered 500, and
 * a form error redirected to recurrence_number=Array. Now a missing or array
 * value counts as not given and any other value is read as a number.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The query string value is cleaned once at the top and only the cleaned
// copy is used: the remaining spots lookup and the hidden field.
function test_calendar_event_edit_reads_recurrence_number_once()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_calendar_event.php');

	$clean = strpos($source, "pg_request_single_values(array('recurrence_number'));");
	$read = strpos($source, "\$_GET['recurrence_number']");
	$liveform = strpos($source, '$liveform = new liveform(');

	pg_assert_true($clean !== false, 'recurrence_number not passed to pg_request_single_values()');
	pg_assert_true(($read !== false) && ($clean < $read), 'recurrence_number read before it is cleaned');
	pg_assert_true(($liveform !== false) && ($clean < $liveform), 'recurrence_number cleaned after the form reads the request');
	pg_assert_true(strpos($source, '(string) max(0, (int) $_GET[\'recurrence_number\'])') !== false, 'recurrence_number not read as a number');

	pg_assert_same(3, substr_count($source, "\$_GET['recurrence_number']"), 'recurrence_number read outside the cleaned copy');
	pg_assert_true(strpos($source, "'name'=>'recurrence_number', 'value'=>\$requested_recurrence_number") !== false, 'hidden field does not use the cleaned value');
	pg_assert_true(strpos($source, '$recurrence_number = $requested_recurrence_number;') !== false, 'remaining spots lookup does not use the cleaned value');
}

// Remaining Spots is written only when the operator changed it, and only
// over the value the screen opened with: a reservation takes spots off
// relatively while the screen is open.
function test_calendar_event_edit_remaining_spots_decision()
{
	if (!function_exists('pg_calendar_event_spots_decision')) {
		require_once(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');
	}

	// Untouched field: the row is left alone, even when checkout moved it.
	pg_assert_same('keep', pg_calendar_event_spots_decision('12', '12', 9), 'untouched spots are kept');
	pg_assert_same('keep', pg_calendar_event_spots_decision(' 12', '12', 12), 'untouched spots with spaces are kept');
	// Changed over the opened value: written.
	pg_assert_same('write', pg_calendar_event_spots_decision('15', '12', 12), 'changed spots over the opened value are written');
	// Changed while checkout moved the stored value: refused.
	pg_assert_same('stale', pg_calendar_event_spots_decision('15', '12', 9), 'changed spots over a moved value are refused');
	// A form opened before the field existed writes as before.
	pg_assert_same('write', pg_calendar_event_spots_decision('12', '', 9), 'a form without the opened value writes');
	pg_assert_same('write', pg_calendar_event_spots_decision('12', null, 9), 'a form without the opened value writes');
}

// The screen keeps the opened value in a hidden field and goes through the
// decision before anything is saved.
function test_calendar_event_edit_keeps_opened_remaining_spots()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_calendar_event.php');

	pg_assert_true(strpos($source, "'name'=>'number_of_remaining_spots_was'") !== false, 'opened remaining spots not printed as a hidden field');
	pg_assert_true(substr_count($source, "assign_field_value('number_of_remaining_spots_was'") >= 3, 'opened remaining spots not assigned on open and on a refusal');

	$decision = strpos($source, 'pg_calendar_event_spots_decision(');
	$update = strpos($source, "UPDATE calendar_events");
	pg_assert_true(($decision !== false) && ($update !== false) && ($decision < $update), 'remaining spots checked after the event is saved');
	pg_assert_true(strpos($source, "\$spots_decision === 'write'") !== false, 'remaining spots written without the decision');
}
