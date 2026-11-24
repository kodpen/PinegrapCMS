<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The walk-in queue (includes/fn/walk_in_queue_*.php) over the external
// API: an application lists the places and a day's numbers, a kiosk
// application takes a number for a visitor, and a desk application calls
// the next person and finishes them.
//
// Every write goes through the queue's own functions: the same hours, cap
// and closing checks as the public page, the same conditional UPDATE that
// lets one desk take a person, and the same notices and queue.* events.
//
// An owner with the user role (3) reaches only the places whose staff list
// names them, as on the desk screen; other places and their numbers are 404.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// A place as the API shows it. $desks is pg_waiting_desks() of the place.
// Pure.
function api_queue_place_present($place, $desks) {

	$services = array();

	foreach ((array)$place['services'] as $item) {

		$services[] = array('key' => (string)$item['key'], 'name' => (string)$item['name'], 'letter' => (string)$item['letter']);

	}

	$out_desks = array();

	foreach ((array)$desks as $desk) {

		$out_desks[] = array(
			'id'       => (int)$desk['id'],
			'name'     => (string)$desk['name'],
			'services' => array_values(array_map('strval', (array)$desk['services'])),
			'state'    => (string)$desk['state'],
			'user_id'  => ((int)$desk['user_id'] > 0) ? (int)$desk['user_id'] : null,
		);

	}

	$text = (string)$place['address'];

	return array(
		'id'       => (int)$place['id'],
		'name'     => (string)$place['name'],
		'address'  => ($text !== '') ? $text : null,
		'status'   => (string)$place['status'],
		'services' => $services,
		'desks'    => $out_desks,
		'waiting'  => (int)($place['waiting'] ?? 0),
		'today'    => (int)($place['today'] ?? 0),
	);

}

// What api_queue_place_present() returns.
function api_queue_place_schema() {

	return array(
		'id'       => 'integer',
		'name'     => 'string',
		'address'  => 'string?',
		'status'   => 'string',
		'services' => 'object[]',
		'desks'    => 'object[]',
		'waiting'  => 'integer',
		'today'    => 'integer',
	);

}

// A number as the API shows it: the queue event's fields with nulls for
// the empty ones. $place is pg_waiting_place_get(), $desk the desk's name.
// The link's token never leaves the site. Pure.
function api_queue_ticket_present($row, $place, $desk) {

	$event = pg_waiting_event_payload($row, $place, $desk);

	foreach (array('service_name', 'priority', 'desk_name', 'name', 'phone', 'email') as $key) {

		if ($event[$key] === '') {

			$event[$key] = null;

		}

	}

	foreach (array('party_size', 'booking_id', 'desk_id', 'staff_user_id', 'contact_id') as $key) {

		if ($event[$key] === 0) {

			$event[$key] = null;

		}

	}

	$event['day'] = preg_replace('/^(\d{4})(\d{2})(\d{2})$/', '$1-$2-$3', (string)(int)$row['day']);
	$event['on_way_at'] = api_time($row['on_way_at']);
	$event['moved_back'] = !empty($row['moved_back']);

	return $event;

}

// What api_queue_ticket_present() returns.
function api_queue_ticket_schema() {

	return array(
		'id'            => 'integer',
		'place_id'      => 'integer',
		'place_name'    => 'string',
		'service'       => 'string',
		'service_name'  => 'string?',
		'number'        => 'string',
		'state'         => 'string',
		'source'        => 'string',
		'priority'      => 'string?',
		'party_size'    => 'integer?',
		'booking_id'    => 'integer?',
		'desk_id'       => 'integer?',
		'desk_name'     => 'string?',
		'staff_user_id' => 'integer?',
		'contact_id'    => 'integer?',
		'name'          => 'string?',
		'phone'         => 'string?',
		'email'         => 'string?',
		'joined_at'     => 'datetime?',
		'called_at'     => 'datetime?',
		'done_at'       => 'datetime?',
		'day'           => 'string',
		'on_way_at'     => 'datetime?',
		'moved_back'    => 'boolean',
	);

}

// A number just taken: the number, the person's page link (made once, as
// the token is not kept) and how many stand ahead of it.
function api_queue_ticket_joined_schema() {

	return array(
		'ticket' => 'object',
		'link'   => 'string',
		'ahead'  => 'integer',
	);

}

// A day of GET /queue/tickets: Y-m-d to YYYYMMDD, today when empty, null
// when it cannot be read. Pure.
function api_queue_day($value, $now) {

	$value = trim((string)$value);

	if ($value === '') {

		return pg_waiting_today($now);

	}

	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match) || !checkdate((int)$match[2], (int)$match[3], (int)$match[1])) {

		return null;

	}

	return (int)($match[1] . $match[2] . $match[3]);

}

// 503 until the upgrade created the tables.
function api_queue_ready_or_fail() {

	if (!pg_waiting_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

}

// Which places the owner reaches: null for every one, the place ids for a
// role-3 staff member. The scope ceiling keeps anyone else out; this is the
// second safety.
function api_queue_scope() {

	$app = api_current_app();

	$scope = pg_waiting_user_desk_scope(array('id' => (int)$app['owner']['id'], 'role' => (int)$app['owner']['role']));

	if ($scope === false) {

		api_fail(403, 'forbidden', lang('Access denied'));

	}

	return $scope;

}

function api_queue_place_or_fail($id, $scope) {

	$place = ((int)$id > 0) ? pg_waiting_place_get($id) : null;

	if (!$place || !pg_waiting_desk_scope_allows($scope, $place['id'])) {

		api_fail_not_found(lang('Place'));

	}

	return $place;

}

function api_queue_ticket_or_fail($id, $scope) {

	$row = api_row("SELECT * FROM waiting_tickets WHERE id = '" . (int)$id . "' LIMIT 1");

	if (!$row || !pg_waiting_desk_scope_allows($scope, $row['place_id'])) {

		api_fail_not_found(lang('Number'));

	}

	return $row;

}

// The number as it stands now, answered.
function api_queue_ticket_answer($id, $status_code = 200) {

	$facts = pg_waiting_event_facts($id);

	if (!$facts) {

		api_fail_not_found(lang('Number'));

	}

	api_ok(api_queue_ticket_present($facts['ticket'], $facts['place'], $facts['desk']), $status_code);

}

function api_queue_places($params) {

	api_queue_ready_or_fail();

	$scope = api_queue_scope();

	$out = array();

	foreach (pg_waiting_place_list() as $place) {

		if (pg_waiting_desk_scope_allows($scope, $place['id'])) {

			$out[] = api_queue_place_present($place, pg_waiting_desks($place['id']));

		}

	}

	api_ok_list($out, count($out));

}

function api_queue_tickets($params) {

	api_queue_ready_or_fail();

	$scope = api_queue_scope();

	$place = api_queue_place_or_fail($params['place_id'], $scope);

	$day = api_queue_day(isset($params['day']) ? $params['day'] : '', time());

	if ($day === null) {

		api_fail_validation(lang('Give the day as YYYY-MM-DD.'), 'day');

	}

	$limit = (int)$params['limit'];

	$where = array("place_id = '" . (int)$place['id'] . "'", "day = '" . (int)$day . "'");

	if (isset($params['state']) && ($params['state'] !== '')) {

		$where[] = "state = '" . e((string)$params['state']) . "'";

	}

	if (isset($params['cursor']) && ($params['cursor'] !== '')) {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "id > '" . (int)$cursor['i'] . "'";

	}

	$rows = api_rows("SELECT * FROM waiting_tickets WHERE " . implode(' AND ', $where) . " ORDER BY id LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$next = api_cursor_encode(0, (int)$rows[count($rows) - 1]['id']);

	}

	$desks = array();

	foreach (pg_waiting_desks($place['id']) as $desk) {

		$desks[$desk['id']] = $desk['name'];

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_queue_ticket_present($row, $place, isset($desks[(int)$row['desk_id']]) ? $desks[(int)$row['desk_id']] : '');

	}

	api_ok_list($out, $limit, $next);

}

// A number taken for a visitor, as the kiosk or staff take one: the place's
// way must be open and its hours, cap and closing time allow it.
function api_queue_tickets_create($params) {

	api_queue_ready_or_fail();

	$scope = api_queue_scope();

	$place = api_queue_place_or_fail($params['place_id'], $scope);

	$way = (isset($params['source']) && ($params['source'] === 'staff')) ? 'staff' : 'kiosk';

	$read = pg_waiting_join_input(array(
		'service'    => isset($params['service']) ? $params['service'] : '',
		'name'       => isset($params['name']) ? $params['name'] : '',
		'phone'      => isset($params['phone']) ? $params['phone'] : '',
		'email'      => isset($params['email']) ? $params['email'] : '',
		'party_size' => isset($params['party_size']) ? $params['party_size'] : '',
		'priority'   => isset($params['priority']) ? $params['priority'] : '',
	), $place, true, false);

	if ($read['error'] !== '') {

		api_fail_validation($read['error']);

	}

	if (isset($params['priority']) && ($params['priority'] !== '') && ($read['values']['priority'] === '')) {

		api_fail_validation(lang('This priority group is not one of the place\'s.'), 'priority');

	}

	// The hours, the cap and the closing time, as the take itself checks
	// them: a rehearsal meets the same 409.
	$check = pg_waiting_take_check($place, $read['values'], $way, time());

	if ($check['error'] !== '') {

		api_fail(409, ($check['error'] === 'way') ? 'queue_closed' : 'join_refused', pg_waiting_refusal_text($check['error'], $place));

	}

	api_dry_run_stop('created', 'queue_ticket', array('place_id' => (int)$place['id'], 'service' => $read['values']['service'], 'source' => $way));

	$taken = pg_waiting_take($place, $read['values'], $way);

	if (!$taken['ok']) {

		if ($taken['error'] === 'write') {

			api_fail(500, 'server_error', lang('The number could not be saved.'));

		}

		api_fail(409, ($taken['error'] === 'way') ? 'queue_closed' : 'join_refused', pg_waiting_refusal_text($taken['error'], $place));

	}

	$facts = pg_waiting_event_facts($taken['id']);

	if (!$facts) {

		api_fail(500, 'server_error', lang('The number could not be saved.'));

	}

	$ahead = (int)pg_waiting_ahead(pg_waiting_place_waiting($place['id'], $facts['ticket']['day']), $taken['id'], (int)$place['settings']['grace_minutes'] * 60);

	api_ok(array(
		'ticket' => api_queue_ticket_present($facts['ticket'], $facts['place'], ''),
		'link'   => pg_waiting_ticket_link(pg_waiting_page_url(), $taken['id'], $taken['token']),
		'ahead'  => $ahead,
	), 201);

}

// "Call next" at an open desk, for whoever has it open.
function api_queue_desks_call($params) {

	api_queue_ready_or_fail();

	$scope = api_queue_scope();

	$row = api_row("SELECT * FROM waiting_desks WHERE id = '" . (int)$params['id'] . "' LIMIT 1");

	if (!$row || !pg_waiting_desk_scope_allows($scope, $row['place_id'])) {

		api_fail_not_found(lang('Desk'));

	}

	$place = api_queue_place_or_fail($row['place_id'], $scope);

	$services = json_decode((string)$row['services'], true);

	$desk = array('id' => (int)$row['id'], 'name' => (string)$row['name'], 'state' => (string)$row['state'], 'user_id' => (int)$row['user_id'], 'services' => is_array($services) ? array_values(array_map('strval', $services)) : array());

	if ($desk['state'] !== 'open') {

		api_fail(409, 'desk_not_open', lang('The desk is not open. Open it on the desk screen first.'));

	}

	if (pg_waiting_desk_current($desk['id'])) {

		api_fail(409, 'desk_busy', lang('Someone is still at the desk. Finish them first.'));

	}

	$next = pg_waiting_next(pg_waiting_desk_waiting($place, $desk['services']), $desk['services'], (int)$place['settings']['grace_minutes'] * 60);

	if ($next === null) {

		api_fail(409, 'nobody_waiting', lang('Nobody is waiting for this desk\'s services.'));

	}

	api_dry_run_stop('called', 'queue_ticket', array('id' => (int)$next['id'], 'desk_id' => $desk['id']));

	$ticket = pg_waiting_call_next($place, $desk, $desk['user_id']);

	if (!$ticket) {

		api_fail(409, 'nobody_waiting', lang('Nobody is waiting for this desk\'s services.'));

	}

	api_queue_ticket_answer($ticket['id']);

}

// "Finish": the number at its desk was served.
function api_queue_tickets_finish($params) {

	api_queue_ready_or_fail();

	$scope = api_queue_scope();

	$row = api_queue_ticket_or_fail($params['id'], $scope);

	if ((string)$row['state'] !== 'called') {

		api_fail(409, 'ticket_state', lang('Only a number called to a desk can be finished.'));

	}

	api_dry_run_stop('updated', 'queue_ticket', array('id' => (int)$row['id'], 'from' => 'called', 'to' => 'served'));

	if (!pg_waiting_finish($row)) {

		api_fail(409, 'ticket_state', lang('Only a number called to a desk can be finished.'));

	}

	api_queue_ticket_answer($row['id']);

}
