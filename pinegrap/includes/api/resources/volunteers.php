<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Volunteer shifts over the API: the programmes (GET /volunteer/programmes),
// their shifts with the places per role (GET /volunteer/shifts) and the
// hours rows (GET /volunteer/hours). Read only, scope customers:read, as a
// volunteer is a contact. An account with the user role reads only the
// programmes it coordinates, as Contacts > Volunteers shows it; another
// programme answers 404. A volunteer's documents, link and messages are
// never given.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_board.php');

// The programmes the API caller may read, id => decoded programme; one
// programme only when $programme_id is above 0. Empty before the update
// with the volunteer tables.
function api_volunteer_programmes_seen($programme_id = 0) {

	if (!pg_volunteer_ready()) {

		return array();

	}

	$owner = api_current_app()['owner'];

	$list = array();

	foreach ((array)db_items("SELECT * FROM volunteer_programmes" . (((int)$programme_id > 0) ? " WHERE id = '" . (int)$programme_id . "'" : '') . " ORDER BY id") as $row) {

		$programme = pg_volunteer_programme_decode($row);

		if (pg_volunteer_user_sees($programme, $owner)) {

			$list[$programme['id']] = $programme + array('created_at' => (int)$row['created_at']);

		}

	}

	return $list;

}

// A day parameter as YYYY-MM-DD, '' when not sent; anything else is 422.
function api_volunteer_day_param($params, $name) {

	$day = isset($params[$name]) ? trim((string)$params[$name]) : '';

	if (($day !== '') && !api_volunteer_day_valid($day)) {

		api_fail_validation(lang(array('string' => '{var:1} must be a day written as YYYY-MM-DD.', 'vars' => $name)), $name);

	}

	return $day;

}

// Whether a text is a real day as YYYY-MM-DD. Pure.
function api_volunteer_day_valid($day) {

	return (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$day, $match) === 1) && checkdate((int)$match[2], (int)$match[3], (int)$match[1]);

}

// The cursor's id, 0 without one; an unreadable cursor is 400.
function api_volunteer_cursor($params) {

	if (!isset($params['cursor']) || ($params['cursor'] === '')) {

		return array(0, 0);

	}

	$cursor = api_cursor_decode($params['cursor']);

	if ($cursor === null) {

		api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

	}

	return array((int)$cursor['v'], (int)$cursor['i']);

}

// GET /volunteer/programmes
function api_volunteer_programmes($params) {

	$limit = (int)$params['limit'];

	$state = isset($params['state']) ? (string)$params['state'] : 'all';

	list(, $after) = api_volunteer_cursor($params);

	$counts = array();

	$programmes = api_volunteer_programmes_seen();

	if ($programmes) {

		foreach ((array)db_items("SELECT programme_id, state, COUNT(*) AS n FROM volunteers WHERE programme_id IN (" . implode(', ', array_keys($programmes)) . ") GROUP BY programme_id, state") as $row) {

			$counts[(int)$row['programme_id']][(string)$row['state']] = (int)$row['n'];

		}

	}

	$out = array();

	$next = null;

	foreach ($programmes as $id => $programme) {

		if (($id <= $after) || (($state !== 'all') && ($programme['state'] !== $state))) {

			continue;

		}

		if (count($out) === $limit) {

			$next = api_cursor_encode(0, $out[$limit - 1]['id']);

			break;

		}

		$out[] = api_volunteer_programme_present($programme, isset($counts[$id]) ? $counts[$id] : array());

	}

	api_ok_list($out, $limit, $next);

}

// One programme of GET /volunteer/programmes. Pure: $programme is
// pg_volunteer_programme_decode() with created_at, $counts state => volunteers.
function api_volunteer_programme_present($programme, $counts) {

	$roles = array();

	foreach ((array)$programme['roles'] as $key => $role) {

		$roles[] = api_volunteer_role_present($key, (array)$role);

	}

	return array(
		'id'            => (int)$programme['id'],
		'name'          => (string)$programme['name'],
		'state'         => (string)$programme['state'],
		'form_page_id'  => ((int)$programme['page_id'] > 0) ? (int)$programme['page_id'] : null,
		'roles'         => $roles,
		'cutoff_hours'  => (int)$programme['cutoff_hours'],
		'min_age'       => (int)$programme['min_age'],
		'swap_approval' => ((int)$programme['swap_approval'] === 1),
		'call_days'     => (int)$programme['call_days'],
		'call_channels' => array_values($programme['call_channels']),
		'coordinator_ids' => array_values(array_map('intval', $programme['coordinators'])),
		'applied'       => (int)($counts['applied'] ?? 0),
		'active'        => (int)($counts['active'] ?? 0),
		'paused'        => (int)($counts['paused'] ?? 0),
		'created_at'    => api_time($programme['created_at'])
	);

}

// One role of a programme: key, name, skills, course_ids (the trainings)
// and documents (the kinds needed). Pure.
function api_volunteer_role_present($key, $role) {

	return array(
		'key'        => (string)$key,
		'name'       => (string)($role['name'] ?? ''),
		'skills'     => array_values(array_map('strval', (array)($role['skills'] ?? array()))),
		'course_ids' => array_values(array_map('intval', (array)($role['trainings'] ?? array()))),
		'documents'  => array_values(array_map('strval', (array)($role['documents'] ?? array())))
	);

}

// What api_volunteer_programme_present() answers with.
function api_volunteer_programme_schema() {

	return array(
		'id'            => 'integer',
		'name'          => 'string',
		'state'         => 'string',
		'form_page_id'  => 'integer?',
		'roles'         => 'object[]',
		'cutoff_hours'  => 'integer',
		'min_age'       => 'integer',
		'swap_approval' => 'boolean',
		'call_days'     => 'integer',
		'call_channels' => 'string[]',
		'coordinator_ids' => 'integer[]',
		'applied'       => 'integer',
		'active'        => 'integer',
		'paused'        => 'integer',
		'created_at'    => 'datetime?'
	);

}

// GET /volunteer/shifts
function api_volunteer_shifts($params) {

	$limit = (int)$params['limit'];

	$programme_id = isset($params['programme_id']) ? (int)$params['programme_id'] : 0;

	$from = api_volunteer_day_param($params, 'from');

	$to = api_volunteer_day_param($params, 'to');

	list($after_start, $after_id) = api_volunteer_cursor($params);

	$programmes = api_volunteer_programmes_seen($programme_id);

	if ($programme_id > 0 && !$programmes) {

		api_fail_not_found(lang('Volunteer programme'));

	}

	if (!$programmes) {

		api_ok_list(array(), $limit, null);

	}

	$from = ($from !== '') ? $from : date('Y-m-d');

	$sql = "SELECT * FROM volunteer_shifts WHERE programme_id IN (" . implode(', ', array_keys($programmes)) . ") AND start_time >= '" . escape($from) . " 00:00:00'";

	if ($to !== '') {

		$sql .= " AND start_time <= '" . escape($to) . " 23:59:59'";

	}

	if (isset($params['place']) && ((string)$params['place'] !== '')) {

		$sql .= " AND place = '" . escape((string)$params['place']) . "'";

	}

	// The cursor is the last shift's start as a unix stamp of the site's
	// time and its id.
	if ($after_id > 0) {

		$start = escape(date('Y-m-d H:i:s', $after_start));

		$sql .= " AND (start_time > '" . $start . "' OR (start_time = '" . $start . "' AND id > '" . $after_id . "'))";

	}

	$shifts = array();

	foreach ((array)db_items($sql . " ORDER BY start_time, id LIMIT " . ($limit + 1)) as $row) {

		$shifts[(int)$row['id']] = pg_volunteer_shift_decode($row);

	}

	$next = null;

	if (count($shifts) > $limit) {

		array_pop($shifts);

		$last = end($shifts);

		$next = api_cursor_encode((int)strtotime($last['start']), $last['id']);

	}

	if ($shifts) {

		foreach ((array)db_items("SELECT shift_id, role, state, COUNT(*) AS n FROM volunteer_signups WHERE shift_id IN (" . implode(', ', array_keys($shifts)) . ") AND state IN ('signed', 'waiting') GROUP BY shift_id, role, state") as $row) {

			$shifts[(int)$row['shift_id']][($row['state'] === 'signed') ? 'filled' : 'waiting'][(string)$row['role']] = (int)$row['n'];

		}

	}

	$out = array();

	foreach ($shifts as $shift) {

		$out[] = api_volunteer_shift_present($shift, $programmes[$shift['programme_id']]);

	}

	api_ok_list($out, $limit, $next);

}

// One shift of GET /volunteer/shifts. Pure: $shift is
// pg_volunteer_shift_decode() with filled and waiting per role.
function api_volunteer_shift_present($shift, $programme) {

	$roles = array();

	foreach ($shift['roles'] as $key => $count) {

		$roles[] = api_volunteer_shift_role_present($shift, $programme, $key, $count);

	}

	return array(
		'id'           => (int)$shift['id'],
		'programme_id' => (int)$shift['programme_id'],
		'place'        => (string)$shift['place'],
		'start_at'     => pg_volunteer_event_time($shift['start']),
		'end_at'       => pg_volunteer_event_time($shift['end']),
		'note'         => ((string)$shift['note'] !== '') ? (string)$shift['note'] : null,
		'series_id'    => ((int)$shift['series_id'] > 0) ? (int)$shift['series_id'] : null,
		'roles'        => $roles,
		'needed'       => pg_volunteer_shift_needed($shift),
		'called'       => count($shift['called'])
	);

}

// One role of a shift: key, name, places, signed and waiting. Pure.
function api_volunteer_shift_role_present($shift, $programme, $key, $count) {

	return array(
		'key'     => (string)$key,
		'name'    => (string)($programme['roles'][$key]['name'] ?? ''),
		'places'  => (int)$count,
		'signed'  => (int)($shift['filled'][$key] ?? 0),
		'waiting' => (int)($shift['waiting'][$key] ?? 0)
	);

}

// What api_volunteer_shift_present() answers with.
function api_volunteer_shift_schema() {

	return array(
		'id'           => 'integer',
		'programme_id' => 'integer',
		'place'        => 'string',
		'start_at'     => 'datetime?',
		'end_at'       => 'datetime?',
		'note'         => 'string?',
		'series_id'    => 'integer?',
		'roles'        => 'object[]',
		'needed'       => 'integer',
		'called'       => 'integer'
	);

}

// GET /volunteer/hours
function api_volunteer_hours($params) {

	$limit = (int)$params['limit'];

	$programme_id = isset($params['programme_id']) ? (int)$params['programme_id'] : 0;

	$status = isset($params['status']) ? (string)$params['status'] : 'approved';

	$from = api_volunteer_day_param($params, 'from');

	$to = api_volunteer_day_param($params, 'to');

	list(, $before) = api_volunteer_cursor($params);

	$programmes = api_volunteer_programmes_seen($programme_id);

	if ($programme_id > 0 && !$programmes) {

		api_fail_not_found(lang('Volunteer programme'));

	}

	if (!$programmes) {

		api_ok_list(array(), $limit, null);

	}

	$day = "COALESCE(DATE(volunteer_shifts.start_time), volunteer_signups.worked_on)";

	// Hours are a signed row of a shift once checked in, or a row outside a
	// shift; approved ones count, pending ones wait for a coordinator.
	$sql = "SELECT volunteer_signups.*, volunteers.contact_id, volunteer_shifts.place, volunteer_shifts.start_time, volunteer_shifts.end_time
		FROM volunteer_signups INNER JOIN volunteers ON volunteers.id = volunteer_signups.volunteer_id
		LEFT JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
		WHERE volunteer_signups.programme_id IN (" . implode(', ', array_keys($programmes)) . ") AND volunteer_signups.state = 'signed'
			AND (volunteer_signups.shift_id IS NULL OR volunteer_signups.check_in IS NOT NULL)"
		. (($status === 'approved') ? ' AND volunteer_signups.approved_at > 0' : (($status === 'pending') ? ' AND volunteer_signups.approved_at = 0' : ''));

	if (!empty($params['volunteer_id'])) {

		$sql .= " AND volunteer_signups.volunteer_id = '" . (int)$params['volunteer_id'] . "'";

	}

	if (!empty($params['contact_id'])) {

		$sql .= " AND volunteers.contact_id = '" . (int)$params['contact_id'] . "'";

	}

	if ($from !== '') {

		$sql .= " AND " . $day . " >= '" . escape($from) . "'";

	}

	if ($to !== '') {

		$sql .= " AND " . $day . " <= '" . escape($to) . "'";

	}

	if ($before > 0) {

		$sql .= " AND volunteer_signups.id < '" . $before . "'";

	}

	$rows = (array)db_items($sql . " ORDER BY volunteer_signups.id DESC LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$next = api_cursor_encode(0, (int)$rows[count($rows) - 1]['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_volunteer_hours_present($row);

	}

	api_ok_list($out, $limit, $next);

}

// One hours row of GET /volunteer/hours. Pure: $row is a volunteer_signups
// row with contact_id and its shift's place, start_time and end_time (null
// outside a shift). Approved rows give the minutes kept at approval; a
// pending one the minutes it would count now.
function api_volunteer_hours_present($row) {

	$shift = ($row['shift_id'] !== null) ? pg_volunteer_shift_decode($row + array('programme_id' => 0, 'roles' => '', 'note' => '', 'series_id' => 0, 'called' => '')) : null;

	$approved = ((int)$row['approved_at'] > 0);

	$id = function ($value) {

		return ((int)$value > 0) ? (int)$value : null;

	};

	return array(
		'id'           => (int)$row['id'],
		'programme_id' => (int)$row['programme_id'],
		'volunteer_id' => (int)$row['volunteer_id'],
		'contact_id'   => $id($row['contact_id']),
		'shift_id'     => ($row['shift_id'] !== null) ? (int)$row['shift_id'] : null,
		'role'         => ((string)$row['role'] !== '') ? (string)$row['role'] : null,
		'place'        => ($shift !== null) ? (string)$row['place'] : null,
		'day'          => substr(($shift !== null) ? (string)$row['start_time'] : (string)$row['worked_on'], 0, 10),
		'checked_in_at'  => pg_volunteer_event_time((string)$row['check_in']),
		'checked_out_at' => pg_volunteer_event_time((string)$row['check_out']),
		'minutes'      => $approved ? (int)$row['minutes'] : min(65535, pg_volunteer_signup_minutes($row, $shift)),
		'note'         => ((string)$row['note'] !== '') ? (string)$row['note'] : null,
		'status'       => $approved ? 'approved' : 'pending',
		'approved_by'  => $id($row['approved_by']),
		'approved_at'  => api_time($row['approved_at'])
	);

}

// What api_volunteer_hours_present() answers with.
function api_volunteer_hours_schema() {

	return array(
		'id'           => 'integer',
		'programme_id' => 'integer',
		'volunteer_id' => 'integer',
		'contact_id'   => 'integer?',
		'shift_id'     => 'integer?',
		'role'         => 'string?',
		'place'        => 'string?',
		'day'          => 'string',
		'checked_in_at'  => 'datetime?',
		'checked_out_at' => 'datetime?',
		'minutes'      => 'integer',
		'note'         => 'string?',
		'status'       => 'string',
		'approved_by'  => 'integer?',
		'approved_at'  => 'datetime?'
	);

}
