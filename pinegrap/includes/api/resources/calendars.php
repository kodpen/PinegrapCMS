<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The site's calendars and their events.
//
// What a booking tool, a newsletter or a mobile app asks of a calendar: which
// calendars there are, and what is on them. An event can be added, changed and
// removed here as far as its words, its times, its calendars and whether it is
// published. Repeats, reservation spots, the product they are sold through,
// the rooms it books and the page a visitor goes to next are built on the
// event's own screen, and are left as they are by a write here.
//
// A repeating event is one row with a rule, and it is answered that way: once,
// with its first start and end and the rule that repeats it. The occurrences are
// not expanded here. The calendar widget expands them against a date range it
// is drawing, and an API that did the same would either cap the range
// arbitrarily or hand back an unbounded list; the rule is the honest answer and
// every calendar library on the other side knows how to read one.
//
// The times are the site's own wall-clock times, as the event screen stores
// them, written with the site's offset so a reader in another timezone places
// them correctly.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// Whether the site runs its calendars at all. Answered 503 rather than with an
// empty list when it does not, so an integration can tell "nothing on" from
// "switched off".
function api_calendars_require() {

	if (defined('CALENDARS') && (CALENDARS !== true)) {

		api_fail(503, 'service_unavailable', lang('Calendars are switched off on this site.'));

	}

}

// The calendars the application's owner may open, as an IN list for SQL, or
// null for all of them. The calendar screens open every calendar to roles 0 to
// 2 and the assigned ones to a user with the calendars right, and an
// application never reads more than the person who owns it.
function api_calendar_ids_visible() {

	static $ids = false;

	if ($ids !== false) {

		return $ids;

	}

	$app = api_current_app();

	if ((int)$app['owner']['role'] < 3) {

		$ids = null;

		return $ids;

	}

	$ids = array();

	foreach (api_rows("SELECT calendar_id FROM users_calendars_xref WHERE user_id = '" . (int)$app['owner']['id'] . "'") as $row) {

		$ids[] = (int)$row['calendar_id'];

	}

	return $ids;

}

// A stored wall-clock time as ISO-8601 with the site's offset, or null for the
// empty date the table defaults to.
function api_calendar_time($value) {

	$value = (string)$value;

	if (($value === '') || (strpos($value, '0000-00-00') === 0)) {

		return null;

	}

	$unix = strtotime($value);

	return ($unix === false) ? null : date('c', $unix);

}

// The repeat rule of an event, or null for one that happens once.
function api_calendar_event_recurrence($row) {

	if ((int)$row['recurrence'] !== 1) {

		return null;

	}

	$rule = array(
		'unit'  => (string)$row['recurrence_type'],
		// How many more times it happens after the first.
		'times' => (int)$row['recurrence_number'],
		'days'  => null,
		'month_by' => null
	);

	// A weekly or daily repeat can be limited to days of the week.
	if (in_array($rule['unit'], array('day', 'week'), true)) {

		$rule['days'] = array();

		foreach (array('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat') as $day) {

			if ((int)$row['recurrence_day_' . $day] === 1) {

				$rule['days'][] = $day;

			}

		}

	}

	// A monthly repeat lands on the same date, or on the same weekday of the
	// same week of the month ("the second Tuesday").
	if ($rule['unit'] === 'month') {

		$rule['month_by'] = ($row['recurrence_month_type'] === 'day_of_the_week') ? 'weekday' : 'date';

	}

	return $rule;

}

function api_calendar_event_select() {

	return "SELECT calendar_events.id, calendar_events.name, calendar_events.published,
			calendar_events.short_description, calendar_events.full_description, calendar_events.location,
			calendar_events.start_time, calendar_events.end_time, calendar_events.all_day,
			calendar_events.show_start_time, calendar_events.show_end_time,
			calendar_events.recurrence, calendar_events.recurrence_number, calendar_events.recurrence_type,
			calendar_events.recurrence_month_type,
			calendar_events.recurrence_day_sun, calendar_events.recurrence_day_mon, calendar_events.recurrence_day_tue,
			calendar_events.recurrence_day_wed, calendar_events.recurrence_day_thu, calendar_events.recurrence_day_fri,
			calendar_events.recurrence_day_sat,
			calendar_events.reservations, calendar_events.separate_reservations, calendar_events.limit_reservations,
			calendar_events.number_of_initial_spots, calendar_events.product_id,
			calendar_events.unpublish_days,
			calendar_events.created_timestamp, calendar_events.last_modified_timestamp
		FROM calendar_events";

}

// Which calendars each of a set of events is on, and the repeats that were
// cancelled, in one read each.
function api_calendar_event_extras($ids) {

	$extras = array('calendars' => array(), 'skipped' => array());

	$ids = array_values(array_unique(array_map('intval', $ids)));

	if (empty($ids)) {

		return $extras;

	}

	$in = implode(',', $ids);

	foreach (api_rows("SELECT calendar_event_id, calendar_id FROM calendar_events_calendars_xref
		WHERE calendar_event_id IN (" . $in . ")
		ORDER BY calendar_event_id ASC, calendar_id ASC") as $row) {

		$extras['calendars'][(int)$row['calendar_event_id']][] = (int)$row['calendar_id'];

	}

	foreach (api_rows("SELECT calendar_event_id, recurrence_number FROM calendar_event_exceptions
		WHERE calendar_event_id IN (" . $in . ")
		ORDER BY calendar_event_id ASC, recurrence_number ASC") as $row) {

		$extras['skipped'][(int)$row['calendar_event_id']][] = (int)$row['recurrence_number'];

	}

	return $extras;

}

// The shape of an event on the wire. $full adds the long description, which a
// page of fifty events does not need.
function api_calendar_event_present($row, $extras = array(), $full = false) {

	$id = (int)$row['id'];

	$out = array(
		'id'                => $id,
		'name'              => (string)$row['name'],
		'published'         => ((int)$row['published'] === 1),
		'short_description' => (string)$row['short_description'],
		'location'          => (string)$row['location'],
		'start'             => api_calendar_time($row['start_time']),
		'end'               => api_calendar_time($row['end_time']),
		'all_day'           => ((int)$row['all_day'] === 1),
		// Whether the site shows the clock time with the date. An all-day
		// event and an event whose time is not announced both hide it.
		'show_start_time'   => ((int)$row['show_start_time'] === 1),
		'show_end_time'     => ((int)$row['show_end_time'] === 1),
		'recurrence'        => api_calendar_event_recurrence($row),
		// The numbers of the repeats that were cancelled: 1 is the first
		// repeat after the original date.
		'skipped_occurrences' => isset($extras['skipped'][$id]) ? $extras['skipped'][$id] : array(),
		'calendar_ids'      => isset($extras['calendars'][$id]) ? $extras['calendars'][$id] : array(),
		'reservations'      => array(
			'enabled'         => ((int)$row['reservations'] === 1),
			'per_occurrence'  => ((int)$row['separate_reservations'] === 1),
			'limited'         => ((int)$row['limit_reservations'] === 1),
			'initial_spots'   => (int)$row['number_of_initial_spots'],
			// The product a reservation is sold through, when it is sold.
			'product_id'      => ((int)$row['product_id'] > 0) ? (int)$row['product_id'] : null
		),
		// How many days after it ends the event leaves the published calendar.
		'unpublish_days'    => (int)$row['unpublish_days'],
		'created_at'        => api_time($row['created_timestamp']),
		'updated_at'        => api_time($row['last_modified_timestamp']),
		'updated_at_unix'   => (int)$row['last_modified_timestamp']
	);

	if ($full) {

		$out['description'] = (string)$row['full_description'];

	}

	return $out;

}

function api_calendars_list($params) {

	api_calendars_require();

	$visible = api_calendar_ids_visible();

	$where = '';

	if ($visible !== null) {

		$where = empty($visible) ? ' WHERE 0' : ' WHERE calendars.id IN (' . implode(',', $visible) . ')';

	}

	$rows = api_rows("SELECT calendars.id, calendars.name, calendars.last_modified_timestamp,
			(SELECT COUNT(*) FROM calendar_events_calendars_xref
				WHERE calendar_events_calendars_xref.calendar_id = calendars.id) AS event_count
		FROM calendars" . $where . "
		ORDER BY calendars.name ASC, calendars.id ASC");

	$out = array();

	foreach ($rows as $row) {

		$out[] = array(
			'id'          => (int)$row['id'],
			'name'        => (string)$row['name'],
			'event_count' => (int)$row['event_count'],
			'updated_at'  => api_time($row['last_modified_timestamp'])
		);

	}

	// A site has a handful of calendars, so they come back in one page.
	api_ok_list($out, max(1, count($out)), null);

}

function api_calendar_events_list($params) {

	api_calendars_require();

	$where = array();

	$visible = api_calendar_ids_visible();

	if (isset($params['calendar_id'])) {

		if (($visible !== null) && !in_array((int)$params['calendar_id'], $visible, true)) {

			api_fail_not_found(lang('Calendar'));

		}

		$where[] = "calendar_events.id IN (SELECT calendar_event_id FROM calendar_events_calendars_xref
			WHERE calendar_id = '" . (int)$params['calendar_id'] . "')";

	} elseif ($visible !== null) {

		$where[] = empty($visible)
			? "0"
			: "calendar_events.id IN (SELECT calendar_event_id FROM calendar_events_calendars_xref
				WHERE calendar_id IN (" . implode(',', $visible) . "))";

	}

	if (isset($params['published'])) {

		$where[] = "calendar_events.published = '" . ($params['published'] ? '1' : '0') . "'";

	}

	// The stored times are the site's wall clock, so the bounds are turned
	// into the same before they are compared.
	if (isset($params['starts_after'])) {

		$where[] = "calendar_events.start_time >= '" . escape(date('Y-m-d H:i:s', (int)$params['starts_after'])) . "'";

	}

	if (isset($params['starts_before'])) {

		$where[] = "calendar_events.start_time < '" . escape(date('Y-m-d H:i:s', (int)$params['starts_before'])) . "'";

	}

	// Still to come, or still running: what a "what's on" list wants. A
	// repeating event counts, because its later occurrences may be.
	if (!empty($params['upcoming'])) {

		$where[] = "(calendar_events.end_time >= '" . escape(date('Y-m-d H:i:s')) . "' OR calendar_events.recurrence = '1')";

	}

	if (isset($params['updated_since'])) {

		$where[] = "calendar_events.last_modified_timestamp >= '" . (int)$params['updated_since'] . "'";

	}

	if (isset($params['search']) && $params['search'] !== '') {

		$search = escape(escape_like($params['search']));

		$where[] = "(calendar_events.name LIKE '%" . $search . "%' OR calendar_events.location LIKE '%" . $search . "%')";

	}

	// Paged in date order, the order a calendar is read in. The cursor holds
	// the start of the last event handed out and its id, which breaks the tie
	// between events that start at the same moment.
	$cursor_clause = '';

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$start = escape(date('Y-m-d H:i:s', $cursor['v']));

		$cursor_clause = "(calendar_events.start_time > '" . $start . "'
			OR (calendar_events.start_time = '" . $start . "' AND calendar_events.id > '" . $cursor['i'] . "'))";

	}

	$paged_where = $where;

	if ($cursor_clause !== '') {

		$paged_where[] = $cursor_clause;

	}

	$where_sql = empty($paged_where) ? '' : ' WHERE ' . implode(' AND ', $paged_where);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = api_rows(api_calendar_event_select() . $where_sql . "
		ORDER BY calendar_events.start_time ASC, calendar_events.id ASC
		LIMIT " . ($limit + 1));

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$next_cursor = null;

	if ($has_more && !empty($rows)) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode((int)strtotime($last['start_time']), $last['id']);

	}

	$total = null;

	if (!empty($params['include_count'])) {

		$total = (int)api_value("SELECT COUNT(*) FROM calendar_events"
			. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)));

	}

	$extras = api_calendar_event_extras(array_map(function ($row) { return $row['id']; }, $rows));

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_calendar_event_present($row, $extras);

	}

	api_ok_list($out, $limit, $next_cursor, $total);

}

function api_calendar_events_get($params) {

	api_calendars_require();

	$id = (int)$params['id'];

	$row = api_row(api_calendar_event_select() . " WHERE calendar_events.id = '" . $id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Calendar event'));

	}

	$extras = api_calendar_event_extras(array($id));

	// An event on none of the owner's calendars is answered like one that does
	// not exist.
	$visible = api_calendar_ids_visible();

	if (($visible !== null) && !array_intersect(isset($extras['calendars'][$id]) ? $extras['calendars'][$id] : array(), $visible)) {

		api_fail_not_found(lang('Calendar event'));

	}

	api_ok(api_calendar_event_present($row, $extras, true));

}

// What api_calendar_event_present() returns, declared for the OpenAPI document.
// description is added by the single-event endpoint only.
function api_calendar_event_schema() {

	return array(
		'id'                => 'integer',
		'name'              => 'string',
		'published'         => 'boolean',
		'short_description' => 'string',
		'location'          => 'string',
		'start'             => 'string?',
		'end'               => 'string?',
		'all_day'           => 'boolean',
		'show_start_time'   => 'boolean',
		'show_end_time'     => 'boolean',
		'recurrence'        => 'CalendarRecurrence?',
		'skipped_occurrences' => 'integer[]',
		'calendar_ids'      => 'integer[]',
		'reservations'      => array(
			'enabled'        => 'boolean',
			'per_occurrence' => 'boolean',
			'limited'        => 'boolean',
			'initial_spots'  => 'integer',
			'product_id'     => 'integer?'
		),
		'unpublish_days'    => 'integer',
		'created_at'        => 'string?',
		'updated_at'        => 'string?',
		'updated_at_unix'   => 'integer',
		'description'       => 'string'
	);

}

// A repeat rule, declared as an object of its own so the event can carry it as
// nullable.
function api_calendar_recurrence_schema() {

	return array(
		'unit'     => 'string',
		'times'    => 'integer',
		'days'     => 'string[]?',
		'month_by' => 'string?'
	);

}

function api_calendar_schema() {

	return array(
		'id'          => 'integer',
		'name'        => 'string',
		'event_count' => 'integer',
		'updated_at'  => 'string?'
	);

}

/* ----- Writes ------------------------------------------------------------- */

// Whether the account behind the application may publish events. Roles 0 to 2
// always may; a user with the calendars right only when publishing was given
// to them as well, and without it the event screens neither publish an event
// nor open a published one for editing.
function api_calendar_owner_publishes() {

	$app = api_current_app();

	if ((int)$app['owner']['role'] < 3) {

		return true;

	}

	$value = api_value("SELECT user_publish_calendar_events FROM user WHERE user_id = '" . (int)$app['owner']['id'] . "' LIMIT 1");

	return in_array(strtolower(trim((string)$value)), array('yes', '1', 'true'), true);

}

function api_calendar_publish_refused() {

	api_fail(403, 'forbidden', lang('The account that owns this application may not publish calendar events.'));

}

// The calendars a write names. Each one has to exist and be one the owner may
// open, and an event is always on at least one.
function api_calendar_ids_in($raw) {

	$ids = array();

	foreach ((array)$raw as $value) {

		$calendar_id = (int)$value;

		if (($calendar_id > 0) && !in_array($calendar_id, $ids, true)) {

			$ids[] = $calendar_id;

		}

	}

	if (empty($ids)) {

		api_fail_validation(lang('Please select at least one calendar.'), 'calendar_ids');

	}

	$found = array();

	foreach (api_rows("SELECT id FROM calendars WHERE id IN (" . implode(',', $ids) . ")") as $row) {

		$found[] = (int)$row['id'];

	}

	$visible = api_calendar_ids_visible();

	foreach ($ids as $calendar_id) {

		if (!in_array($calendar_id, $found, true) || (($visible !== null) && !in_array($calendar_id, $visible, true))) {

			api_fail_validation(lang(array(
				'string' => 'There is no calendar with the id {var:1} that this application may use.',
				'vars'   => array($calendar_id)
			)), 'calendar_ids');

		}

	}

	return $ids;

}

// The start and end an event is stored with, from two moments, the way the
// event screens store them: an all-day event runs from 00:00 on its first day
// to 23:59 on its last, and any other event ends after it starts. Returns the
// two wall-clock times, or null when the end comes too early.
function api_calendar_event_times($start, $end, $all_day) {

	if ($all_day) {

		$first = date('Y-m-d', (int)$start);

		$last = date('Y-m-d', (int)$end);

		if ($last < $first) {

			return null;

		}

		return array($first . ' 00:00:00', $last . ' 23:59:00');

	}

	if ((int)$end <= (int)$start) {

		return null;

	}

	return array(date('Y-m-d H:i:s', (int)$start), date('Y-m-d H:i:s', (int)$end));

}

function api_calendar_event_times_refused($all_day) {

	api_fail_validation($all_day
		? lang('Please enter an end date that is on or after the start date.')
		: lang('Please enter an end date & time that is after the start date & time.'), 'end');

}

// The event a write is about, answered like one that does not exist when it is
// on none of the owner's calendars, and closed to an owner who may not
// publish once it is published, as its screen is.
function api_calendar_event_writable_or_fail($id) {

	$id = (int)$id;

	$row = api_row(api_calendar_event_select() . " WHERE calendar_events.id = '" . $id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Calendar event'));

	}

	$extras = api_calendar_event_extras(array($id));

	$on = isset($extras['calendars'][$id]) ? $extras['calendars'][$id] : array();

	$visible = api_calendar_ids_visible();

	if (($visible !== null) && !array_intersect($on, $visible)) {

		api_fail_not_found(lang('Calendar event'));

	}

	if (((int)$row['published'] === 1) && !api_calendar_owner_publishes()) {

		api_fail(403, 'forbidden', lang('This event is published, and the account that owns this application may not publish calendar events, so it cannot change or remove it.'));

	}

	$row['calendar_ids'] = $on;

	return $row;

}

// The rooms an event books (the locations on its screen) are kept free of
// double bookings by that screen, and a one-off event moved here is checked
// the same way. A repeating event is checked repeat by repeat on its own
// screen, so its times are not moved from here while it books a room.
function api_calendar_event_rooms_or_fail($row, $times) {

	$rooms = api_rows("SELECT calendar_event_locations.id, calendar_event_locations.name
		FROM calendar_events_calendar_event_locations_xref
		INNER JOIN calendar_event_locations
			ON calendar_event_locations.id = calendar_events_calendar_event_locations_xref.calendar_event_location_id
		WHERE calendar_events_calendar_event_locations_xref.calendar_event_id = '" . (int)$row['id'] . "'");

	if (empty($rooms)) {

		return;

	}

	if ((int)$row['recurrence'] === 1) {

		api_fail(409, 'repeating_event_books_room', lang('This repeating event books a room. Move it on its own screen, which checks every repeat against the room\'s other bookings.'), 'start');

	}

	foreach ($rooms as $room) {

		if (check_calendar_event_location_availability($room['id'], $times[0], $times[1], (string)(int)$row['id']) !== 'available') {

			api_fail(409, 'room_taken', lang(array(
				'string' => 'The room {var:1} is booked by another event at that time.',
				'vars'   => array((string)$room['name'])
			)), 'start');

		}

	}

}

// The words and switches a write sends, as columns. Publishing is refused to
// an owner who may not publish.
function api_calendar_event_fields_in($params) {

	$set = array();

	if (isset($params['name'])) {

		if (trim((string)$params['name']) === '') {

			api_fail_validation(lang('Name is required.'), 'name');

		}

		$set['name'] = trim((string)$params['name']);

	}

	foreach (array('short_description' => 'short_description', 'location' => 'location') as $param => $column) {

		if (isset($params[$param])) {

			$set[$column] = (string)$params[$param];

		}

	}

	// The long description is printed as it is on the public calendar, so what
	// arrives from outside is cut down to the formatting the editor makes.
	if (isset($params['description'])) {

		$set['full_description'] = prepare_rich_text_editor_content_for_input(pg_sanitize_rich_text((string)$params['description']));

	}

	foreach (array('show_start_time', 'show_end_time') as $switch) {

		if (isset($params[$switch])) {

			$set[$switch] = $params[$switch] ? '1' : '0';

		}

	}

	if (isset($params['published']) || isset($params['unpublish_days'])) {

		if (!api_calendar_owner_publishes()) {

			api_calendar_publish_refused();

		}

		if (isset($params['published'])) {

			$set['published'] = $params['published'] ? '1' : '0';

		}

		if (isset($params['unpublish_days'])) {

			$set['unpublish_days'] = (string)(int)$params['unpublish_days'];

		}

	}

	return $set;

}

function api_calendar_event_answer($id, $status = 200) {

	$id = (int)$id;

	$row = api_row(api_calendar_event_select() . " WHERE calendar_events.id = '" . $id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Calendar event'));

	}

	api_ok(api_calendar_event_present($row, api_calendar_event_extras(array($id)), true), $status);

}

function api_calendar_events_create($params) {

	api_calendars_require();

	$app = api_current_app();

	$set = api_calendar_event_fields_in($params);

	if (!isset($set['name'])) {

		api_fail_validation(lang('Name is required.'), 'name');

	}

	$calendar_ids = api_calendar_ids_in(isset($params['calendar_ids']) ? $params['calendar_ids'] : array());

	$all_day = !empty($params['all_day']);

	$times = api_calendar_event_times($params['start'], $params['end'], $all_day);

	if ($times === null) {

		api_calendar_event_times_refused($all_day);

	}

	// The event screen's starting values for everything not sent: shown with
	// its times, unpublished, gone from the published calendar 45 days after
	// it ends, and no repeat, reservation or room.
	$set += array(
		'published'         => '0',
		'unpublish_days'    => '45',
		'short_description' => '',
		'full_description'  => '',
		'location'          => '',
		'show_start_time'   => '1',
		'show_end_time'     => '1'
	);

	$set += array(
		'all_day'                    => $all_day ? '1' : '0',
		'start_time'                 => $times[0],
		'end_time'                   => $times[1],
		'notes'                      => '',
		'recurrence'                 => '0',
		'recurrence_number'          => '0',
		'recurrence_type'            => 'day',
		'recurrence_day_sun'         => '1',
		'recurrence_day_mon'         => '1',
		'recurrence_day_tue'         => '1',
		'recurrence_day_wed'         => '1',
		'recurrence_day_thu'         => '1',
		'recurrence_day_fri'         => '1',
		'recurrence_day_sat'         => '1',
		'recurrence_month_type'      => 'day_of_the_month',
		'reservations'               => '0',
		'separate_reservations'      => '1',
		'limit_reservations'         => '1',
		'number_of_initial_spots'    => '1',
		'no_remaining_spots_message' => '<p>' . lang('Sorry, we are not accepting any more reservations at this time.') . '</p>',
		'reserve_button_label'       => lang('Reserve'),
		'product_id'                 => '0',
		'next_page_id'               => '0'
	);

	api_dry_run_stop('created', 'calendar_event', array(
		'name'         => $set['name'],
		'start'        => api_calendar_time($times[0]),
		'end'          => api_calendar_time($times[1]),
		'calendar_ids' => $calendar_ids,
		'published'    => ($set['published'] === '1')
	));

	$owner_id = (int)$app['owner']['id'];

	$columns = array();

	foreach ($set as $column => $value) {

		$columns[] = $column . " = '" . escape((string)$value) . "'";

	}

	api_exec("INSERT INTO calendar_events SET " . implode(', ', $columns) . ",
		created_user_id = '" . $owner_id . "', created_timestamp = UNIX_TIMESTAMP(),
		last_modified_user_id = '" . $owner_id . "', last_modified_timestamp = UNIX_TIMESTAMP()");

	$id = api_insert_id();

	if ($id <= 0) {

		api_fail_server();

	}

	foreach ($calendar_ids as $calendar_id) {

		api_exec("INSERT INTO calendar_events_calendars_xref (calendar_event_id, calendar_id)
			VALUES ('" . $id . "', '" . (int)$calendar_id . "')");

	}

	log_activity(lang(array(
		'string' => 'Calendar event ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($set['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_calendar_event_answer($id, 201);

}

function api_calendar_events_update($params) {

	api_calendars_require();

	$app = api_current_app();

	$row = api_calendar_event_writable_or_fail($params['id']);

	$id = (int)$row['id'];

	$set = api_calendar_event_fields_in($params);

	// The times are worked out again from what is sent and what is stored
	// whenever one of the three changes, so switching all_day alone turns the
	// stored times into whole days and back.
	if (isset($params['start']) || isset($params['end']) || isset($params['all_day'])) {

		$all_day = isset($params['all_day']) ? (bool)$params['all_day'] : ((int)$row['all_day'] === 1);

		$start = isset($params['start']) ? (int)$params['start'] : (int)strtotime($row['start_time']);

		$end = isset($params['end']) ? (int)$params['end'] : (int)strtotime($row['end_time']);

		$times = api_calendar_event_times($start, $end, $all_day);

		if ($times === null) {

			api_calendar_event_times_refused($all_day);

		}

		if (($times[0] !== date('Y-m-d H:i:s', strtotime($row['start_time']))) || ($times[1] !== date('Y-m-d H:i:s', strtotime($row['end_time'])))) {

			api_calendar_event_rooms_or_fail($row, $times);

		}

		$set['all_day'] = $all_day ? '1' : '0';
		$set['start_time'] = $times[0];
		$set['end_time'] = $times[1];

	}

	// The calendars sent replace the ones the owner may open; a calendar the
	// owner cannot see keeps the event, as on the event screen.
	$calendars = null;

	if (isset($params['calendar_ids'])) {

		$calendars = api_calendar_ids_in($params['calendar_ids']);

	}

	if (empty($set) && ($calendars === null)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'calendar_event', array(
		'id'      => $id,
		'changes' => array_merge(array_keys($set), ($calendars === null) ? array() : array('calendar_ids'))
	));

	if (!empty($set)) {

		$columns = array();

		foreach ($set as $column => $value) {

			$columns[] = $column . " = '" . escape((string)$value) . "'";

		}

		api_exec("UPDATE calendar_events SET " . implode(', ', $columns) . ",
				last_modified_user_id = '" . (int)$app['owner']['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . $id . "'");

	} else {

		api_exec("UPDATE calendar_events SET last_modified_user_id = '" . (int)$app['owner']['id'] . "',
				last_modified_timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . $id . "'");

	}

	if ($calendars !== null) {

		$visible = api_calendar_ids_visible();

		foreach (array_diff($row['calendar_ids'], $calendars) as $calendar_id) {

			if (($visible === null) || in_array((int)$calendar_id, $visible, true)) {

				api_exec("DELETE FROM calendar_events_calendars_xref
					WHERE calendar_event_id = '" . $id . "' AND calendar_id = '" . (int)$calendar_id . "'");

			}

		}

		foreach (array_diff($calendars, $row['calendar_ids']) as $calendar_id) {

			api_exec("INSERT INTO calendar_events_calendars_xref (calendar_event_id, calendar_id)
				VALUES ('" . $id . "', '" . (int)$calendar_id . "')");

		}

	}

	log_activity(lang(array(
		'string' => 'Calendar event ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array(isset($set['name']) ? $set['name'] : (string)$row['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_calendar_event_answer($id);

}

// With what goes with it, as on the event screen: the cancelled repeats, its
// calendars and rooms, and the spots left for reservations.
function api_calendar_events_delete($params) {

	api_calendars_require();

	$app = api_current_app();

	$row = api_calendar_event_writable_or_fail($params['id']);

	$id = (int)$row['id'];

	api_dry_run_stop('deleted', 'calendar_event', array('id' => $id, 'name' => (string)$row['name']));

	api_exec("DELETE FROM calendar_events WHERE id = '" . $id . "'");

	api_exec("DELETE FROM calendar_event_exceptions WHERE calendar_event_id = '" . $id . "'");

	api_exec("DELETE FROM calendar_events_calendars_xref WHERE calendar_event_id = '" . $id . "'");

	api_exec("DELETE FROM calendar_events_calendar_event_locations_xref WHERE calendar_event_id = '" . $id . "'");

	api_exec("DELETE FROM remaining_reservation_spots WHERE calendar_event_id = '" . $id . "'");

	log_activity(lang(array(
		'string' => 'Calendar event ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array((string)$row['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true));

}
