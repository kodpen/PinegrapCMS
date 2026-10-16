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
