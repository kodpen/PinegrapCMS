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
// calendars there are, and what is on them. Read only. An event is built on its
// own screen with repeats, reservation spots, a product to sell them through
// and the page a visitor goes to next, and that screen is where it is written.
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

		$search = escape($params['search']);

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
