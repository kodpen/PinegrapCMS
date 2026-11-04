<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the calendar writes over the external API (POST /calendars,
 * POST /calendars/{id}, DELETE /calendars/{id} and its POST twin) and the
 * write path they share with add_calendar.php and edit_calendar.php
 * (includes/fn/calendar_writes.php): the route rows and their scope, the
 * name limit against its column, the user-role rules, the checks before the
 * dry-run stop, the screens calling the shared functions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/calendars.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function api_calendar_writes_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function api_calendar_writes_test_body($relative, $name)
{
	$source = api_calendar_writes_test_source($relative);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_api_calendar_writes_routes()
{
	$expected = array(
		'calendars.create'      => array('POST', '/calendars', 'api_calendars_create'),
		'calendars.update'      => array('POST', '/calendars/{id}', 'api_calendars_update'),
		'calendars.delete'      => array('DELETE', '/calendars/{id}', 'api_calendars_delete'),
		'calendars.delete_post' => array('POST', '/calendars/{id}/delete', 'api_calendars_delete'),
	);

	$found = array();

	foreach (api_schema() as $route) {
		if (!isset($expected[$route['id']])) {
			continue;
		}

		$found[] = $route['id'];
		$row = $expected[$route['id']];

		pg_assert_same($row[0], $route['method'], $route['id'] . ' method');
		pg_assert_same($row[1], $route['path'], $route['id'] . ' path');
		pg_assert_same($row[2], $route['handler'], $route['id'] . ' handler');
		pg_assert_same('calendars:write', $route['scope'], $route['id'] . ' scope');
		pg_assert_true(function_exists($route['handler']), $route['handler'] . '() exists');
		pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');

		foreach ($route['params'] as $param) {
			if ($param['name'] === 'name') {
				// calendars.name is varchar(100).
				pg_assert_same(100, $param['max_length'] ?? null, $route['id'] . ' name fits its column');
			}
		}
	}

	pg_assert_same(count($expected), count($found), 'every calendar write route is declared');
}

// The update answers PATCH too, never relied on (IIS answers it 405).
function test_api_calendar_writes_update_takes_patch()
{
	foreach (api_schema() as $route) {
		if ($route['id'] === 'calendars.update') {
			pg_assert_same(array('PATCH'), $route['also_accepts'] ?? null, 'POST /calendars/{id} also accepts PATCH');
		}
	}
}

function test_api_calendar_writes_presenter()
{
	pg_assert_same(
		array('id' => 4, 'name' => 'Courses', 'event_count' => 3, 'updated_at' => null),
		api_calendar_present(array('id' => '4', 'name' => 'Courses', 'event_count' => '3', 'last_modified_timestamp' => '0')),
		'a calendar row on the wire'
	);

	pg_assert_same(array_keys(api_calendar_schema()), array_keys(api_calendar_present(array('id' => 1, 'name' => '', 'event_count' => 0, 'last_modified_timestamp' => 0))), 'the presenter answers the declared fields');
}

// add_calendar.php is closed to the user role, so the API refuses it the
// create; rename and delete reach only the calendars assigned to it (404).
function test_api_calendar_writes_user_role_rules()
{
	$file = 'includes/api/resources/calendars.php';
	$create = api_calendar_writes_test_body($file, 'api_calendars_create');

	pg_assert_contains("(int)\$app['owner']['role'] >= 3", $create, 'create refuses the user role');
	pg_assert_true(strpos($create, "api_fail(403") < strpos($create, 'api_dry_run_stop('), 'the role is refused before the dry-run stop');

	$get = api_calendar_writes_test_body($file, 'api_calendar_or_fail');

	pg_assert_contains('api_calendar_ids_visible()', $get, 'a calendar outside the assigned ones is not found');

	foreach (array('api_calendars_update', 'api_calendars_delete') as $handler) {
		$body = api_calendar_writes_test_body($file, $handler);

		pg_assert_contains("api_calendar_or_fail(\$params['id'])", $body, $handler . ' applies the row rule');
	}
}

// Every check runs before the dry-run stop, and the write goes through the
// functions the screens call.
function test_api_calendar_writes_check_before_stop()
{
	$file = 'includes/api/resources/calendars.php';

	$create = api_calendar_writes_test_body($file, 'api_calendars_create');
	pg_assert_true(strpos($create, 'api_calendar_name_in(') < strpos($create, 'api_dry_run_stop('), 'create checks the name first');
	pg_assert_true(strpos($create, 'api_dry_run_stop(') < strpos($create, 'pg_calendar_save(0,'), 'create writes after the stop');

	$update = api_calendar_writes_test_body($file, 'api_calendars_update');
	pg_assert_true(strpos($update, 'api_calendar_name_in(') < strpos($update, 'api_dry_run_stop('), 'update checks the name first');
	pg_assert_true(strpos($update, 'api_dry_run_stop(') < strpos($update, 'pg_calendar_save($id,'), 'update writes after the stop');

	$delete = api_calendar_writes_test_body($file, 'api_calendars_delete');
	pg_assert_true(strpos($delete, "'calendar_has_events'") < strpos($delete, 'api_dry_run_stop('), 'delete refuses a calendar with events first');
	pg_assert_true(strpos($delete, 'api_dry_run_stop(') < strpos($delete, 'pg_calendar_delete($id)'), 'delete writes after the stop');

	$name = api_calendar_writes_test_body($file, 'api_calendar_name_in');
	pg_assert_contains('pg_calendar_name_taken($name, $except_id)', $name, 'the name check is the screens\' lookup');
	pg_assert_contains("api_fail(409, 'name_taken'", $name, 'a taken name is 409 name_taken');
}

function test_api_calendar_writes_screens_share_the_write_path()
{
	pg_assert_contains('pg_calendar_save(0, ', api_calendar_writes_test_source('add_calendar.php'), 'add_calendar.php creates through the shared function');

	$edit = api_calendar_writes_test_source('edit_calendar.php');

	pg_assert_contains('pg_calendar_save($id, ', $edit, 'edit_calendar.php renames through the shared function');
	pg_assert_contains('pg_calendar_delete($id)', $edit, 'edit_calendar.php deletes through the shared function');
	pg_assert_contains('pg_calendar_event_count($id)', $edit, 'edit_calendar.php counts the events the shared way');
	pg_assert_false(strpos($edit, 'DELETE FROM') !== false, 'edit_calendar.php holds no delete of its own');

	$delete = api_calendar_writes_test_body('includes/fn/calendar_writes.php', 'pg_calendar_delete');

	foreach (array('users_calendars_xref', 'calendar_views_calendars_xref', 'calendar_event_views_calendars_xref', 'calendar_events_calendars_xref') as $table) {
		pg_assert_contains("'" . $table . "'", $delete, 'a deleted calendar leaves no ' . $table . ' rows');
	}
}
