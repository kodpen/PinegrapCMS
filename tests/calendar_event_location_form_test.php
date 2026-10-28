<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the calendar event location form shared by the create and the
 * edit screens (includes/forms/calendar_event_location_form.php): both
 * screens use the shared form and keep their own gates.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_calendar_event_location_form_screens_use_shared_form()
{
	foreach (array('add_calendar_event_location.php', 'edit_calendar_event_location.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/calendar_event_location_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_calendar_event_location_form_card($liveform)', $source, $file . ' prints the shared card');
		pg_assert_contains('pg_calendar_event_location_form_name_in_use(', $source, $file . ' checks the name the shared way');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains("\$user['role'] == 3", $source, $file . ' keeps the role 3 refusal');
		pg_assert_false(strpos($source, "'name'=>'name', 'id'=>'name'") !== false, $file . ' keeps no copy of the card');
	}
}

function test_calendar_event_location_form_defines_its_functions()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_location_form.php');

	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR'))", $source, 'the form starts with its gate');
	pg_assert_contains('function pg_calendar_event_location_form_card($liveform)', $source, 'the card builder is defined');
	pg_assert_contains('function pg_calendar_event_location_form_name_in_use($name, $id)', $source, 'the name check is defined');
}
