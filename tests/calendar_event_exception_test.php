<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the parameters of a repeating event instance's Remove / Unremove
 * link (update_calendar_event_exception.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_calendar_event_exception_request_reads_a_link()
{
	pg_assert_same(
		array('action' => 'create', 'calendar_event_id' => 12, 'recurrence_number' => 0),
		pg_calendar_event_exception_request(array('action' => 'create', 'calendar_event_id' => '12', 'recurrence_number' => '0', 'token' => 'x')));
	pg_assert_same('delete', pg_calendar_event_exception_request(array('action' => 'delete', 'calendar_event_id' => '3', 'recurrence_number' => '2'))['action']);
}

function test_calendar_event_exception_request_refuses_missing_or_wrong_values()
{
	// Only the token: a warning and a blank page before.
	pg_assert_same(null, pg_calendar_event_exception_request(array('token' => 'x')));
	pg_assert_same(null, pg_calendar_event_exception_request(array('action' => 'bogus', 'calendar_event_id' => '3', 'recurrence_number' => '1')));
	pg_assert_same(null, pg_calendar_event_exception_request(array('action' => 'create', 'calendar_event_id' => '0', 'recurrence_number' => '1')));
	pg_assert_same(null, pg_calendar_event_exception_request(array('action' => 'create', 'calendar_event_id' => '3', 'recurrence_number' => '-1')));
	pg_assert_same(null, pg_calendar_event_exception_request(array('action' => 'create', 'calendar_event_id' => '3x', 'recurrence_number' => '1')));
	pg_assert_same(null, pg_calendar_event_exception_request(array('action' => 'create', 'calendar_event_id' => array('3'), 'recurrence_number' => '1')));
}

function test_calendar_event_exception_number_must_be_an_instance_of_the_event()
{
	pg_assert_true(pg_calendar_event_exception_number_ok(0, '1', '3'));
	pg_assert_true(pg_calendar_event_exception_number_ok(3, '1', '3'));
	pg_assert_false(pg_calendar_event_exception_number_ok(99, '1', '3'), 'a 3-repeat event has no instance 99');
	pg_assert_false(pg_calendar_event_exception_number_ok(1, '0', '0'), 'an event that does not repeat');
}

function test_calendar_event_exception_create_is_not_written_twice()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/update_calendar_event_exception.php');

	pg_assert_true(strpos($source, '$_GET[\'calendar_event_id\']') === false, 'the id is read through the request check only');
	pg_assert_true(strpos($source, 'SELECT 1 FROM calendar_event_exceptions') < strpos($source, 'INSERT INTO calendar_event_exceptions'), 'an existing exception is looked for first');
}
