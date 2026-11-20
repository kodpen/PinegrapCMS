<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The tickets of a calendar event (includes/fn/event_tickets.php): what a
// door app or a box office report reads. A ticket reaches a person only
// through its order, so the route sits under orders:read, and the event
// follows the calendar row rule as its reservations do.
//
// A ticket's code and its hash are never read here: the code opens the door,
// and the hash finds the ticket that does. The presenter names its fields
// one by one, so a column added to the table is not handed out by accident.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The tickets table is there, or 503: an integration can tell "no tickets
// sold" from "this site has not been updated yet".
function api_event_tickets_require() {

	api_calendars_require();

	if (!function_exists('pg_event_tickets_ready') || !pg_event_tickets_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));

	}

}

// The shape of a ticket on the wire. Pure.
function api_event_ticket_present($row) {

	$used_at = (int)($row['used_at'] ?? 0);
	$used_by = (int)($row['used_by'] ?? 0);
	$product_id = (int)($row['product_id'] ?? 0);
	$order_number = (int)($row['order_number'] ?? 0);

	return array(
		'id'                => (int)$row['id'],
		'order_id'          => (int)$row['order_id'],
		'order_number'      => ($order_number > 0) ? $order_number : null,
		'order_status'      => (string)($row['status'] ?? ''),
		'order_item_id'     => (int)$row['order_item_id'],
		'unit'              => (int)$row['unit'],
		'product_id'        => ($product_id > 0) ? $product_id : null,
		'calendar_event_id' => (int)$row['calendar_event_id'],
		'recurrence_number' => (int)$row['recurrence_number'],
		'state'             => (string)$row['state'],
		'guest_name'        => (string)($row['guest_name'] ?? ''),
		'checked_in_at'     => ($used_at > 0) ? api_time($used_at) : null,
		'checked_in_by'     => ($used_by > 0) ? $used_by : null,
		'voided_at'         => api_time((int)($row['voided_at'] ?? 0)),
		'created_at'        => api_time((int)($row['created_at'] ?? 0))
	);

}

function api_event_ticket_schema() {

	return array(
		'id'                => 'integer',
		'order_id'          => 'integer',
		'order_number'      => 'integer?',
		'order_status'      => 'string',
		'order_item_id'     => 'integer',
		'unit'              => 'integer',
		'product_id'        => 'integer?',
		'calendar_event_id' => 'integer',
		'recurrence_number' => 'integer',
		'state'             => 'string',
		'guest_name'        => 'string',
		'checked_in_at'     => 'datetime?',
		'checked_in_by'     => 'integer?',
		'voided_at'         => 'datetime?',
		'created_at'        => 'datetime?'
	);

}

// The counts beside a page of tickets, from state => number rows. Sold is
// every ticket not void; checked in is every used one. Pure.
function api_event_ticket_counts($rows) {

	$by_state = array('valid' => 0, 'used' => 0, 'void' => 0);

	foreach ((array)$rows as $row) {

		$state = (string)$row['state'];

		if (isset($by_state[$state])) {

			$by_state[$state] += (int)$row['n'];

		}

	}

	return array(
		'sold'       => $by_state['valid'] + $by_state['used'],
		'checked_in' => $by_state['used'],
		'void'       => $by_state['void']
	);

}

function api_event_ticket_counts_schema() {

	return array(
		'sold'       => 'integer',
		'checked_in' => 'integer',
		'void'       => 'integer'
	);

}

// The tickets of an event, oldest first, cursor paged, with the counts of
// the event (or of the repeat asked for) whatever the state filter.
function api_calendar_event_tickets($params) {

	api_event_tickets_require();

	$event = api_calendar_event_visible_or_fail($params['id']);

	$where = array("event_tickets.calendar_event_id = '" . (int)$event['id'] . "'");

	if (isset($params['recurrence_number'])) {

		$where[] = "event_tickets.recurrence_number = '" . (int)$params['recurrence_number'] . "'";

	}

	$counts = api_event_ticket_counts(api_rows("SELECT event_tickets.state, COUNT(*) AS n
		FROM event_tickets
		WHERE " . implode(' AND ', $where) . "
		GROUP BY event_tickets.state"));

	if (isset($params['state']) && ($params['state'] !== '')) {

		$where[] = "event_tickets.state = '" . escape($params['state']) . "'";

	}

	$paged = $where;

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$paged[] = "event_tickets.id > '" . (int)$cursor['i'] . "'";

	}

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = api_rows("SELECT event_tickets.id, event_tickets.order_id, event_tickets.order_item_id, event_tickets.unit,
			event_tickets.product_id, event_tickets.calendar_event_id, event_tickets.recurrence_number,
			event_tickets.state, event_tickets.used_at, event_tickets.used_by, event_tickets.voided_at,
			event_tickets.created_at, orders.order_number, orders.status,
			orders.billing_first_name, orders.billing_last_name
		FROM event_tickets
		LEFT JOIN orders ON orders.id = event_tickets.order_id
		WHERE " . implode(' AND ', $paged) . "
		ORDER BY event_tickets.id ASC
		LIMIT " . ($limit + 1));

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$next_cursor = null;

	if ($has_more && !empty($rows)) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode((int)$last['id'], $last['id']);

	}

	$total = null;

	if (!empty($params['include_count'])) {

		$total = (int)api_value("SELECT COUNT(*) FROM event_tickets WHERE " . implode(' AND ', $where));

	}

	// The guest's name from the unit's product form, else the buyer's: one
	// query for the page (pg_check_in_with_names()).
	if ($rows) {

		require_once(PG_FUNCTIONS_DIR . '/includes/fn/event_check_in.php');

		$rows = pg_check_in_with_names($rows);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_event_ticket_present($row);

	}

	api_ok_list($out, $limit, $next_cursor, $total, array('counts' => $counts));

}
