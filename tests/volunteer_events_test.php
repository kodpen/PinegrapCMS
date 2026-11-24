<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the volunteer shift events (includes/fn/volunteer_events.php)
 * and GET /volunteer/programmes, /volunteer/shifts and /volunteer/hours
 * (includes/api/resources/volunteers.php): each event's keys and values,
 * the moments in UTC, the places a shift still needs, the six events in the
 * webhook, Workspace and flow catalogues, where each is announced, and the
 * presenters of the three routes.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/volunteers.php');

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		$unix = (int) $unix;

		return ($unix <= 0) ? null : gmdate('Y-m-d\TH:i:s\Z', $unix);
	}
}

function volunteer_events_test_events()
{
	return array('volunteer.applied', 'volunteer.approved', 'shift.signed_up', 'shift.unfilled', 'volunteer.checked_in', 'volunteer.hours_approved');
}

function volunteer_events_test_row(array $more = array())
{
	return array_merge(array('volunteer_id' => 41, 'signup_id' => 940, 'shift_id' => 77, 'programme_id' => 3, 'programme' => 'Food bank', 'contact_id' => 884, 'form_id' => 0,
		'skills' => '["Driving","First aid"]', 'applied_at' => 1000, 'approved_at' => 0, 'approved_by' => 12, 'role' => 'r1', 'programme_roles' => '{"r1":{"name":"Driver"}}',
		'place' => 'Warehouse', 'start_time' => '2026-12-05 09:00:00', 'end_time' => '2026-12-05 14:00:00', 'how' => 'waitlist', 'check_in' => '2026-12-05 08:56:00',
		'checked_by' => 0, 'minutes' => 300, 'worked_on' => null, 'needed' => 2, 'asked' => 6), $more);
}

function test_volunteer_event_time()
{
	pg_assert_same('1970-01-01T00:16:40Z', pg_volunteer_event_time(1000), 'a unix stamp');
	pg_assert_same('1970-01-01T00:16:40Z', pg_volunteer_event_time('1000'), 'a stamp as text');
	pg_assert_same(null, pg_volunteer_event_time(0), 'no stamp');
	pg_assert_same(null, pg_volunteer_event_time(''), 'no time');
	pg_assert_same(null, pg_volunteer_event_time(null), 'a NULL column');
	pg_assert_same(gmdate('Y-m-d\TH:i:s\Z', strtotime('2026-12-05 09:00:00')), pg_volunteer_event_time('2026-12-05 09:00:00'), 'a wall-clock time in the site\'s zone');
}

function test_volunteer_event_payloads()
{
	$row = volunteer_events_test_row();

	$applied = pg_volunteer_event_payload('volunteer.applied', $row);
	pg_assert_same(41, $applied['id'], 'applied: the profile');
	pg_assert_same(null, $applied['form_id'], 'applied: no entry is null');
	pg_assert_same('Driving, First aid', $applied['skills'], 'applied: the skills as text');

	$approved = pg_volunteer_event_payload('volunteer.approved', $row);
	pg_assert_same(12, $approved['approved_by'], 'approved: by whom');
	pg_assert_same(null, $approved['approved_at'], 'approved: no stamp is null');

	$signed = pg_volunteer_event_payload('shift.signed_up', $row);
	pg_assert_same(940, $signed['id'], 'signed up: the sign-up');
	pg_assert_same('Driver', $signed['role_name'], 'signed up: the role\'s name');
	pg_assert_same('waitlist', $signed['how'], 'signed up: how');
	pg_assert_same('sign_up', pg_volunteer_event_payload('shift.signed_up', volunteer_events_test_row(array('how' => 'other')))['how'], 'an unknown how is a plain sign-up');
	pg_assert_same('', pg_volunteer_event_payload('shift.signed_up', volunteer_events_test_row(array('role' => 'r9')))['role_name'], 'a removed role has no name');

	$unfilled = pg_volunteer_event_payload('shift.unfilled', $row);
	pg_assert_same(77, $unfilled['id'], 'unfilled: the shift');
	pg_assert_same(array(2, 6), array($unfilled['needed'], $unfilled['asked']), 'unfilled: needed and asked');

	$checked = pg_volunteer_event_payload('volunteer.checked_in', $row);
	pg_assert_same(null, $checked['checked_by'], 'a scan has no coordinator');

	$hours = pg_volunteer_event_payload('volunteer.hours_approved', $row);
	pg_assert_same('2026-12-05', $hours['day'], 'hours: the shift\'s day');
	pg_assert_same(300, $hours['minutes'], 'hours: the minutes');

	$outside = pg_volunteer_event_payload('volunteer.hours_approved', volunteer_events_test_row(array('shift_id' => null, 'start_time' => null, 'worked_on' => '2026-11-30')));
	pg_assert_same(array(null, '2026-11-30'), array($outside['shift_id'], $outside['day']), 'hours outside a shift: no shift, the entered day');

	pg_assert_same(array(), pg_volunteer_event_payload('volunteer.other', $row), 'an unknown event has no data');
}

// Every payload has the fields and the order of its sample.
function test_volunteer_event_payloads_match_their_samples()
{
	$payloads = api_webhook_event_payloads_site();

	foreach (volunteer_events_test_events() as $event) {
		pg_assert_true(isset($payloads[$event]), $event . ' has fields and a sample');
		pg_assert_same(array_keys($payloads[$event]['fields']), array_keys(pg_volunteer_event_payload($event, volunteer_events_test_row())), $event . ': the keys written');
		pg_assert_same(array_keys($payloads[$event]['fields']), array_keys($payloads[$event]['sample']), $event . ': the sample');
	}
}

function test_volunteer_shift_needed()
{
	pg_assert_same(3, pg_volunteer_shift_needed(array('roles' => array('r1' => 3, 'r2' => 2), 'filled' => array('r1' => 1, 'r2' => 1))), 'the free places of each role');
	pg_assert_same(0, pg_volunteer_shift_needed(array('roles' => array('r1' => 1), 'filled' => array('r1' => 2))), 'never below 0');
	pg_assert_same(2, pg_volunteer_shift_needed(array('roles' => array('r1' => 2))), 'no sign-ups yet');
}

function test_volunteer_events_in_the_catalogues()
{
	$webhooks = api_webhook_core_events();
	$workspace = pg_event_workspace_events();
	$watch = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
	$anchors = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');

	foreach (volunteer_events_test_events() as $event) {
		pg_assert_true(isset($webhooks[$event]), $event . ' in the webhook catalogue');
		pg_assert_true(in_array($event, $workspace, true), $event . ' kept for the Workspace');
		pg_assert_contains("\$catalog['" . $event . "']", $watch, $event . ' in the flow start catalogue');
		pg_assert_contains("'" . $event . "'", $anchors, $event . ' has its flow outputs');
		pg_assert_same('Volunteers', api_app_event_area($event), $event . ' is in the Volunteers area');
	}
}

// Each event is announced by the write it tells of, after it; the places
// taken under a shift's lock after its release.
function test_volunteer_events_announced_where_written()
{
	$source = function ($file) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/' . $file);
	};

	pg_assert_contains("pg_volunteer_announce('volunteer.applied'", $source('volunteer_programmes.php'), 'an application');
	pg_assert_contains("pg_volunteer_announce('volunteer.approved'", $source('volunteer_programmes.php'), 'an approval');
	pg_assert_contains("pg_volunteer_announce('volunteer.checked_in'", $source('volunteer_hours.php'), 'a check-in');
	pg_assert_contains("pg_volunteer_announce('volunteer.hours_approved'", $source('volunteer_hours.php'), 'approved hours');
	pg_assert_contains('pg_volunteer_announce_unfilled(', $source('volunteer_messages.php'), 'a short shift\'s call');

	$signup = $source('volunteer_signup.php');

	foreach (array('function pg_volunteer_self_act', 'function pg_volunteer_swap_decide') as $function) {
		$body = substr($signup, strpos($signup, $function));
		$body = substr($body, 0, strpos($body, "\n}\n"));
		$release = strpos($body, 'RELEASE_LOCK');
		$announce = strpos($body, "pg_volunteer_announce('shift.signed_up'");

		pg_assert_true(($release !== false) && ($announce !== false) && ($announce > $release), $function . ' announces after the lock');
	}
}

function test_api_volunteer_programme_present()
{
	$programme = array('id' => 3, 'name' => 'Food bank', 'state' => 'open', 'page_id' => 0, 'roles' => array('r1' => array('name' => 'Driver', 'skills' => array('Driving'), 'trainings' => array('4'), 'documents' => array('Licence'))),
		'cutoff_hours' => 24, 'min_age' => 18, 'swap_approval' => 1, 'call_days' => 3, 'call_channels' => array('email', 'sms'), 'coordinators' => array(12), 'created_at' => 0);
	$out = api_volunteer_programme_present($programme, array('active' => 5, 'applied' => 2));

	pg_assert_same(array_keys(api_volunteer_programme_schema()), array_keys($out), 'the declared fields');
	pg_assert_same(null, $out['form_page_id'], 'no form is null');
	pg_assert_same(array(array('key' => 'r1', 'name' => 'Driver', 'skills' => array('Driving'), 'course_ids' => array(4), 'documents' => array('Licence'))), $out['roles'], 'the roles');
	pg_assert_same(array(2, 5, 0), array($out['applied'], $out['active'], $out['paused']), 'the counts');
	pg_assert_same(true, $out['swap_approval'], 'swap approval as a boolean');
}

function test_api_volunteer_shift_present()
{
	$shift = array('id' => 77, 'programme_id' => 3, 'place' => 'Warehouse', 'start' => '2026-12-05 09:00:00', 'end' => '2026-12-05 14:00:00', 'note' => '', 'series_id' => 0,
		'roles' => array('r1' => 3), 'called' => array(5, 6), 'filled' => array('r1' => 1), 'waiting' => array('r1' => 2));
	$out = api_volunteer_shift_present($shift, array('roles' => array('r1' => array('name' => 'Driver'))));

	pg_assert_same(array_keys(api_volunteer_shift_schema()), array_keys($out), 'the declared fields');
	pg_assert_same(array(array('key' => 'r1', 'name' => 'Driver', 'places' => 3, 'signed' => 1, 'waiting' => 2)), $out['roles'], 'the places per role');
	pg_assert_same(array(null, null, 2, 2), array($out['note'], $out['series_id'], $out['needed'], $out['called']), 'empty note and series, needed and called');
}

function test_api_volunteer_hours_present()
{
	$row = array('id' => 940, 'programme_id' => 3, 'volunteer_id' => 41, 'contact_id' => 884, 'shift_id' => 77, 'role' => 'r1', 'place' => 'Warehouse', 'start_time' => '2026-12-05 09:00:00',
		'end_time' => '2026-12-05 14:00:00', 'worked_on' => null, 'check_in' => '2026-12-05 09:10:00', 'check_out' => '2026-12-05 13:10:00', 'minutes' => 0, 'note' => '',
		'approved_at' => 0, 'approved_by' => 0);
	$pending = api_volunteer_hours_present($row);

	pg_assert_same(array_keys(api_volunteer_hours_schema()), array_keys($pending), 'the declared fields');
	pg_assert_same(array('pending', 240, '2026-12-05'), array($pending['status'], $pending['minutes'], $pending['day']), 'pending: the minutes it would count');

	$approved = api_volunteer_hours_present(array_merge($row, array('approved_at' => 2000, 'approved_by' => 12, 'minutes' => 230)));
	pg_assert_same(array('approved', 230, 12), array($approved['status'], $approved['minutes'], $approved['approved_by']), 'approved: the minutes kept');

	$outside = api_volunteer_hours_present(array_merge($row, array('shift_id' => null, 'role' => '', 'place' => null, 'start_time' => null, 'end_time' => null, 'worked_on' => '2026-11-30',
		'check_in' => null, 'check_out' => null, 'minutes' => 90, 'note' => 'Sorting')));
	pg_assert_same(array(null, null, null, '2026-11-30', 90, 'Sorting'), array($outside['shift_id'], $outside['role'], $outside['place'], $outside['day'], $outside['minutes'], $outside['note']), 'outside a shift');
}

function test_api_volunteer_day_valid()
{
	pg_assert_true(api_volunteer_day_valid('2026-12-05'), 'a day');
	pg_assert_false(api_volunteer_day_valid('2026-02-30'), 'no such day');
	pg_assert_false(api_volunteer_day_valid('5.12.2026'), 'another form');
}
