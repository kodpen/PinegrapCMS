<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Appointment bookings (includes/fn/bookings.php): the staff agenda over the
// external API. An application lists and reads bookings, asks for a day's
// free times, books one for a customer as the agenda does, moves a booking's
// status and cancels it.
//
// Every write goes through the agenda's own functions
// (includes/fn/booking_agenda.php): the same checks, the same slot engine,
// the same INSERT under the staff member's lock (a slot taken meanwhile is
// refused), the same status UPDATE that names the status it moves from, and
// the same audit trail line. booking.* events and the customer's messages
// come from pg_booking_changed() (includes/fn/booking_messages.php).
//
// An owner with the user role (3) reaches only their own staff member's
// bookings, as on the agenda; rows of another are 404.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');

// A booking row as the API shows it. $services and $staff are
// pg_booking_agenda_services() and pg_booking_agenda_staff(). The manage
// token never leaves the site. Pure.
function api_booking_present($row, $services, $staff) {

	$service_id = (int)$row['service_id'];

	$staff_id = (int)$row['staff_id'];

	$text = function ($value) {

		$value = (string)$value;

		return ($value !== '') ? $value : null;

	};

	// The deposit order and its payment (pg_booking_deposit_rows() reads
	// the number); null before the deposit columns.
	$deposit = pg_booking_deposit_view($row);

	return array(
		'id'           => (int)$row['id'],
		'service_id'   => $service_id,
		'service_name' => isset($services[$service_id]) ? (string)$services[$service_id]['name'] : null,
		'staff_id'     => $staff_id,
		'staff_name'   => isset($staff[$staff_id]) ? (string)$staff[$staff_id]['name'] : null,
		'user_id'      => ((int)$row['user_id'] > 0) ? (int)$row['user_id'] : null,
		'name'         => (string)$row['name'],
		'email'        => $text($row['email']),
		'phone'        => $text($row['phone']),
		'notes'        => $text($row['notes']),
		'start_at'     => api_time($row['start_at']),
		'end_at'       => api_time($row['end_at']),
		'status'       => (string)$row['status'],
		'source'       => (string)$row['source'],
		'hold_until'   => ((string)$row['status'] === 'held') ? api_time($row['hold_until']) : null,
		'order_id'     => ($deposit['order_id'] > 0) ? $deposit['order_id'] : null,
		'order_number' => ($deposit['order_number'] !== '') ? $deposit['order_number'] : null,
		'paid_at'      => ($deposit['paid_at'] > 0) ? api_time($deposit['paid_at']) : null,
		'created_at'   => api_time($row['created_at']),
		'updated_at'   => api_time($row['updated_at']),
	);

}

// What api_booking_present() returns.
function api_booking_schema() {

	return array(
		'id'           => 'integer',
		'service_id'   => 'integer',
		'service_name' => 'string?',
		'staff_id'     => 'integer',
		'staff_name'   => 'string?',
		'user_id'      => 'integer?',
		'name'         => 'string',
		'email'        => 'string?',
		'phone'        => 'string?',
		'notes'        => 'string?',
		'start_at'     => 'string?',
		'end_at'       => 'string?',
		'status'       => 'string',
		'source'       => 'string',
		'hold_until'   => 'string?',
		'order_id'     => 'integer?',
		'order_number' => 'string?',
		'paid_at'      => 'string?',
		'created_at'   => 'string?',
		'updated_at'   => 'string?',
	);

}

// One free time of GET /bookings/slots.
function api_booking_slot_schema() {

	return array(
		'staff_id'   => 'integer',
		'staff_name' => 'string',
		'start_at'   => 'string',
		'end_at'     => 'string',
	);

}

// The free times of a day as the API lists them: by start, then staff
// member. $starts is staff id => the unix starts pg_booking_slots() offered.
// Pure.
function api_booking_slots_present($starts, $service, $staff) {

	$length = (int)$service['length_minutes'] * 60;

	$out = array();

	foreach ($starts as $staff_id => $list) {

		foreach ((array)$list as $start) {

			$out[] = array(
				'staff_id'   => (int)$staff_id,
				'staff_name' => isset($staff[$staff_id]) ? (string)$staff[$staff_id]['name'] : '',
				'start_at'   => api_time($start),
				'end_at'     => api_time((int)$start + $length),
				'_start'     => (int)$start,
			);

		}

	}

	usort($out, function ($a, $b) {

		return ($a['_start'] <=> $b['_start']) ?: ($a['staff_id'] <=> $b['staff_id']);

	});

	foreach ($out as $key => $slot) {

		unset($out[$key]['_start']);

	}

	return $out;

}

// The statuses GET /bookings lists when none is asked for: every one but a
// visitor's hold. Pure.
function api_booking_list_statuses($value) {

	$all = pg_booking_statuses();

	$value = trim((string)$value);

	if ($value === '') {

		return array_values(array_diff($all, array('held')));

	}

	$asked = array();

	foreach (explode(',', $value) as $status) {

		$status = trim($status);

		if (!in_array($status, $all, true)) {

			return null;

		}

		$asked[] = $status;

	}

	return array_values(array_unique($asked));

}

// The event a status move announces: a booking becoming confirmed or
// cancelled. A hold confirmed is announced by pg_booking_confirm() itself,
// and a hold cancelled was never announced. Pure.
function api_booking_move_event($from, $to) {

	if (($from === $to) || ($from === 'held')) {

		return '';

	}

	return in_array($to, array('confirmed', 'cancelled'), true) ? $to : '';

}

// 503 until the upgrade created the tables.
function api_bookings_ready_or_fail() {

	if (!pg_booking_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

}

// Which staff member's bookings the owner reaches: null for every one, a
// staff id for a role-3 staff member. The scope ceiling keeps anyone else
// out; this is the second safety.
function api_bookings_scope() {

	$app = api_current_app();

	$scope = pg_booking_agenda_user_scope($app['owner']);

	if ($scope === false) {

		api_fail(403, 'forbidden', lang('Access denied'));

	}

	return $scope;

}

function api_booking_load_or_fail($id, $scope) {

	$row = api_row("SELECT * FROM bookings WHERE bookings.id = '" . (int)$id . "'" . pg_booking_agenda_scope_sql($scope) . " LIMIT 1");

	if (!$row) {

		api_fail_not_found(lang('Booking'));

	}

	return $row;

}

function api_booking_answer($id, $scope, $status_code = 200) {

	$row = api_booking_load_or_fail($id, $scope);
	$row = pg_booking_deposit_rows(array($row))[0];

	api_ok(api_booking_present($row, pg_booking_agenda_services(), pg_booking_agenda_staff()), $status_code);

}

// The enabled staff members the owner may book for, by id.
function api_bookings_offered_staff($scope) {

	$out = array();

	foreach (pg_booking_agenda_staff() as $id => $member) {

		if ($member['enabled'] && (($scope === null) || ($id === $scope))) {

			$out[$id] = $member;

		}

	}

	return $out;

}

// The enabled services, by id.
function api_bookings_offered_services() {

	$out = array();

	foreach (pg_booking_agenda_services() as $id => $service) {

		if ((int)$service['enabled'] === 1) {

			$out[$id] = $service;

		}

	}

	return $out;

}

function api_bookings_list($params) {

	api_bookings_ready_or_fail();

	$scope = api_bookings_scope();

	$statuses = api_booking_list_statuses(isset($params['status']) ? $params['status'] : '');

	if ($statuses === null) {

		api_fail_validation(lang(array('string' => '{var:1} must be one of: {var:2}', 'vars' => array('status', implode(', ', pg_booking_statuses())))), 'status');

	}

	$where = array("bookings.status IN ('" . implode("', '", $statuses) . "')");

	if (isset($params['staff_id'])) {

		$where[] = "bookings.staff_id = '" . (int)$params['staff_id'] . "'";

	}

	if (isset($params['service_id'])) {

		$where[] = "bookings.service_id = '" . (int)$params['service_id'] . "'";

	}

	if (isset($params['from'])) {

		$where[] = "bookings.start_at >= '" . (int)$params['from'] . "'";

	}

	if (isset($params['to'])) {

		$where[] = "bookings.start_at <= '" . (int)$params['to'] . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "(bookings.start_at > '" . (int)$cursor['v'] . "' OR (bookings.start_at = '" . (int)$cursor['v'] . "' AND bookings.id > '" . (int)$cursor['i'] . "'))";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows("SELECT * FROM bookings
		WHERE " . implode(' AND ', $where) . pg_booking_agenda_scope_sql($scope) . "
		ORDER BY bookings.start_at, bookings.id
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['start_at'], (int)$last['id']);

	}

	$services = pg_booking_agenda_services();

	$staff = pg_booking_agenda_staff();

	$out = array();

	foreach (pg_booking_deposit_rows($rows) as $row) {

		$out[] = api_booking_present($row, $services, $staff);

	}

	api_ok_list($out, $limit, $next);

}

function api_bookings_get($params) {

	api_bookings_ready_or_fail();

	api_booking_answer($params['id'], api_bookings_scope());

}

function api_bookings_slots($params) {

	api_bookings_ready_or_fail();

	$scope = api_bookings_scope();

	$day = (string)$params['day'];

	if (!pg_booking_date_valid($day)) {

		api_fail_validation(lang(array('string' => '{var:1} must be a date, in ISO-8601 or YYYY-MM-DD form.', 'vars' => 'day')), 'day');

	}

	$services = api_bookings_offered_services();

	$service_id = (int)$params['service'];

	if (!isset($services[$service_id])) {

		api_fail_not_found(lang('Service'));

	}

	$staff = api_bookings_offered_staff($scope);

	if (isset($params['staff'])) {

		if (!isset($staff[(int)$params['staff']])) {

			api_fail_not_found(lang('Staff member'));

		}

		$staff = array((int)$params['staff'] => $staff[(int)$params['staff']]);

	}

	$now = time();

	$starts = array();

	foreach ($staff as $id => $member) {

		if (in_array($service_id, $member['service_ids'], true)) {

			$starts[$id] = pg_booking_agenda_free_slots($services[$service_id], $member, $day, $now);

		}

	}

	api_ok(array(
		'day'        => $day,
		'timezone'   => date_default_timezone_get(),
		'service_id' => $service_id,
		'slots'      => api_booking_slots_present($starts, $services[$service_id], $staff),
	));

}

// A booking for a customer, as the agenda makes it: the time must be one
// GET /bookings/slots offers for its day.
function api_bookings_create($params) {

	api_bookings_ready_or_fail();

	$scope = api_bookings_scope();

	$fields = array(
		'service_id' => (int)$params['service_id'],
		'staff_id'   => (int)$params['staff_id'],
		'start_at'   => (int)$params['start_at'],
		'name'       => isset($params['name']) ? (string)$params['name'] : '',
		'email'      => isset($params['email']) ? (string)$params['email'] : '',
		'phone'      => isset($params['phone']) ? (string)$params['phone'] : '',
		'notes'      => isset($params['notes']) ? (string)$params['notes'] : '',
	);

	$services = api_bookings_offered_services();

	$staff = api_bookings_offered_staff($scope);

	foreach (pg_booking_agenda_check($fields, $services, $staff) as $field => $message) {

		api_fail_validation($message, $field);

	}

	$now = time();

	$service = $services[$fields['service_id']];

	if (!in_array($fields['start_at'], pg_booking_agenda_free_slots($service, $staff[$fields['staff_id']], date('Y-m-d', $fields['start_at']), $now), true)) {

		api_fail(409, 'slot_taken', lang('This time has just been taken. Choose another one.'), 'start_at');

	}

	$status = (isset($params['status']) && ($params['status'] === 'requested')) ? 'requested' : 'confirmed';

	api_dry_run_stop('created', 'booking', array('status' => $status, 'end_at' => api_time($fields['start_at'] + ((int)$service['length_minutes'] * 60))));

	$written = pg_booking_confirm(array('service' => $service) + $fields, $status, $now, 'api');

	if ($written['answer'] === 'taken') {

		api_fail(409, 'slot_taken', lang('This time has just been taken. Choose another one.'), 'start_at');

	}

	if ($written['answer'] !== 'ok') {

		api_fail(500, 'server_error', lang('The booking could not be saved.'));

	}

	pg_booking_agenda_audit('create', $written['id'], array(), (array)api_row("SELECT * FROM bookings WHERE id = '" . (int)$written['id'] . "'"));

	api_booking_answer($written['id'], $scope, 201);

}

// The booking a status move is for, once the agenda offers the move for it
// now: 409 booking_state otherwise.
function api_bookings_move_check($params, $status, $scope) {

	api_bookings_ready_or_fail();

	$row = api_booking_load_or_fail($params['id'], $scope);

	if (!in_array($status, pg_booking_agenda_moves_now($row, time()), true)) {

		api_fail(409, 'booking_state', lang('This status cannot be chosen for the booking.'));

	}

	return $row;

}

// Moves the booking through the agenda's write; $reason is a cancel's
// reason, added to the notes.
function api_bookings_move_write($row, $status, $reason, $scope) {

	$now = time();

	$customer = pg_booking_customer($row);

	if ($status === 'cancelled') {

		$customer['notes'] = pg_booking_cancel_notes($customer['notes'], $reason, lang('Cancelled'));

	}

	// A live hold confirmed goes through the visitor's confirm, which stores
	// the manage token and announces the booking.
	if (($row['status'] === 'held') && ($status === 'confirmed')) {

		$written = pg_booking_confirm(array('id' => (int)$row['id']) + $customer, $status, $now, 'api');

		$done = ($written['answer'] === 'ok');

	} else {

		$done = pg_booking_agenda_status_write($row, $status, $customer, $now);

	}

	if (!$done) {

		api_fail(409, 'booking_state', lang('The booking was changed meanwhile. Check it and save again.'));

	}

	pg_booking_agenda_audit(($status === 'cancelled') ? 'cancel' : 'update', $row['id'], $row, (array)api_row("SELECT * FROM bookings WHERE id = '" . (int)$row['id'] . "'"));

	$event = api_booking_move_event((string)$row['status'], $status);

	if ($event !== '') {

		pg_booking_changed((int)$row['id'], $event);

	}

	api_booking_answer($row['id'], $scope);

}

function api_bookings_status($params) {

	$scope = api_bookings_scope();

	$status = (string)$params['status'];

	$row = api_bookings_move_check($params, $status, $scope);

	api_dry_run_stop('updated', 'booking', array('id' => (int)$row['id'], 'from' => (string)$row['status'], 'to' => $status));

	api_bookings_move_write($row, $status, '', $scope);

}

function api_bookings_cancel($params) {

	$scope = api_bookings_scope();

	$row = api_bookings_move_check($params, 'cancelled', $scope);

	api_dry_run_stop('cancelled', 'booking', array('id' => (int)$row['id'], 'from' => (string)$row['status']));

	api_bookings_move_write($row, 'cancelled', isset($params['reason']) ? (string)$params['reason'] : '', $scope);

}
