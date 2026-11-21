<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The problems the hourly delivery pass found on parcels (shipment_problems),
// as Orders > Delivery problems lists them: stuck, not handed over, a
// delivery problem or returned. Read only: assigning, the note and closing
// stay on the screen. A problem carries the province of its address, never
// the street address or the buyer's name.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/delivery_problems.php');

// GET /shipments/problems
function api_shipments_problems($params) {

	$filters = array(
		'view'    => isset($params['status']) ? (string)$params['status'] : 'open',
		'kind'    => 0,
		'carrier' => isset($params['carrier']) ? (string)$params['carrier'] : ''
	);

	$codes = pg_delivery_tower_kind_codes();

	if (isset($params['kind']) && isset($codes[$params['kind']])) {

		$filters['kind'] = (int)$codes[$params['kind']];

	}

	list($where, $values) = pg_delivery_problem_where($filters);

	if (isset($params['order_id'])) {

		$where .= ' AND (shipment_problems.order_id = ?)';

		$values[] = (int)$params['order_id'];

	}

	if (isset($params['cursor']) && ($params['cursor'] !== '')) {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where .= ' AND (shipment_problems.id < ?)';

		$values[] = (int)$cursor['i'];

	}

	$limit = (int)$params['limit'];

	// Before the update that adds the table there are no problems to list.
	$rows = pg_delivery_problems_ready()
		? _pg_delivery_problems_rows(_pg_delivery_problems_select() . "
			WHERE " . $where . "
			ORDER BY shipment_problems.id DESC
			LIMIT " . ($limit + 1), $values)
		: array();

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$next = api_cursor_encode(0, (int)$rows[count($rows) - 1]['id']);

	}

	$summary = pg_delivery_tower_summary();

	$figures = is_array($summary) ? $summary['figures'] : array();

	$now = time();

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_shipment_problem_present($row, $figures, $now);

	}

	api_ok_list($out, $limit, $next);

}

// How a closed problem closed, as a word: sent_again, refunded, found or
// other when staff closed it; delivered, parcel_removed, order_cancelled,
// scanned (a label not handed over was scanned) or status_changed when a
// later status settled it; null while it is open. Pure.
function api_shipment_problem_close_reason($row) {

	if ((int)$row['closed_at'] <= 0) {

		return null;

	}

	$reason = array_search((int)$row['close_reason'], pg_delivery_tower_close_reasons(), true);

	if (($reason !== false) && ($reason !== 'status') && ($reason !== 'open')) {

		return (string)$reason;

	}

	if ($row['parcel_id'] === null) {

		return 'parcel_removed';

	}

	if (((int)$row['delivered_at'] > 0) || ((string)$row['status'] === 'delivered')) {

		return 'delivered';

	}

	$order_status = (string)($row['order_status'] ?? '');

	if (($order_status === '') || in_array($order_status, array('incomplete', 'cancelled'), true)) {

		return 'order_cancelled';

	}

	return ((int)$row['kind'] === pg_delivery_tower_kind_codes()['not_handed_over']) ? 'scanned' : 'status_changed';

}

// One problem of GET /shipments/problems. Pure: $row is a row of
// _pg_delivery_problems_select(), $figures the nightly summary's.
function api_shipment_problem_present($row, $figures, $now) {

	$parcel = ($row['parcel_id'] !== null);

	$state = trim((string)($row['state'] ?? ''));

	$city = trim((string)($row['city'] ?? ''));

	$province = ($state !== '') ? $state : $city;

	$carrier = $parcel ? (string)$row['carrier'] : '';

	$kind = array_search((int)$row['kind'], pg_delivery_tower_kind_codes(), true);

	$estimate = (string)($row['delivery_date'] ?? '');

	$transit = $parcel ? pg_delivery_problem_transit_days($row, $now) : null;

	$usual = $parcel ? pg_delivery_problem_usual_days(array('figures' => $figures), $carrier, $state, $city) : null;

	return array(
		'id'              => (int)$row['id'],
		'kind'            => ($kind === false) ? '' : (string)$kind,
		'open'            => ((int)$row['closed_at'] <= 0),
		'found_at'        => api_time($row['found_at']),
		'closed_at'       => api_time($row['closed_at']),
		'close_reason'    => api_shipment_problem_close_reason($row),
		'assigned_to'     => ((int)$row['assigned_to'] > 0) ? (int)$row['assigned_to'] : null,
		'assignee'        => (((int)$row['assigned_to'] > 0) && ((string)($row['assignee'] ?? '') !== '')) ? (string)$row['assignee'] : null,
		'note'            => (string)$row['note'],
		'order_id'        => (int)$row['order_id'],
		'order_number'    => ((string)($row['order_number'] ?? '') !== '') ? (string)$row['order_number'] : null,
		'order_type'      => ((string)($row['order_type'] ?? '') !== '') ? (string)$row['order_type'] : null,
		'shipment_id'     => $parcel ? (int)$row['parcel_id'] : null,
		'number'          => $parcel ? (string)$row['number'] : null,
		'carrier'         => ($carrier !== '') ? $carrier : null,
		'carrier_name'    => ($carrier !== '') ? pg_delivery_problem_carrier_name($carrier) : null,
		'status'          => $parcel ? (string)$row['status'] : null,
		'province'        => ($province !== '') ? $province : null,
		'shipped_at'      => $parcel ? api_time($row['shipped_at']) : null,
		'delivered_at'    => $parcel ? api_time($row['delivered_at']) : null,
		'estimated_date'  => preg_match('/^\d{4}-\d{2}-\d{2}$/', $estimate) && ($estimate !== '0000-00-00') ? $estimate : null,
		'days_in_transit' => $transit,
		'usual_days'      => $usual
	);

}

// What api_shipment_problem_present() answers with, declared for the
// OpenAPI document.
function api_shipment_problem_schema() {

	return array(
		'id'              => 'integer',
		'kind'            => 'string',
		'open'            => 'boolean',
		'found_at'        => 'datetime?',
		'closed_at'       => 'datetime?',
		'close_reason'    => 'string?',
		'assigned_to'     => 'integer?',
		'assignee'        => 'string?',
		'note'            => 'string',
		'order_id'        => 'integer',
		'order_number'    => 'string?',
		'order_type'      => 'string?',
		'shipment_id'     => 'integer?',
		'number'          => 'string?',
		'carrier'         => 'string?',
		'carrier_name'    => 'string?',
		'status'          => 'string?',
		'province'        => 'string?',
		'shipped_at'      => 'datetime?',
		'delivered_at'    => 'datetime?',
		'estimated_date'  => 'date?',
		'days_in_transit' => 'integer?',
		'usual_days'      => 'number?'
	);

}
