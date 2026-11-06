<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Customer returns across every order (view_returns.php, view_return.php):
// the list a helpdesk or fulfilment tool works through, one return, and the
// moves staff make on its screen. The writes are the screen's own
// (pg_return_staff_move(), pg_return_refund() in includes/fn/returns.php), so
// the screen's rules, mails, audit lines and order.return.* events hold. A
// return is shaped by api_order_return_present() (resources/orders.php), the
// object GET /orders/{id}/returns answers too.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The actions POST /returns/{id} takes, each with the status it moves the
// return to: the buttons of view_return.php but the return label, which may
// cost money at the carrier and stays on the screen.
function api_return_actions() {

	return array(
		'approve' => 'approved',
		'reject'  => 'rejected',
		'ship'    => 'shipped',
		'receive' => 'received',
		'close'   => 'closed',
		'refund'  => 'refunded'
	);

}

// Which body fields each action reads; a field sent with another action is
// refused rather than dropped in silence.
function api_return_action_fields() {

	return array(
		'approve' => array('note', 'carrier', 'carrier_code'),
		'reject'  => array('note'),
		'ship'    => array('tracking_number', 'carrier'),
		'receive' => array(),
		'close'   => array(),
		'refund'  => array('amount', 'method', 'reference')
	);

}

// The WHERE parts of GET /returns. status is one stored status or open (the
// panel's default: requested, approved, shipped, received); modified_since
// keeps the returns changed at or after it (order_returns.updated_at).
function api_returns_where($params) {

	$where = array();

	if (isset($params['modified_since'])) {

		$where[] = "updated_at >= '" . (int)$params['modified_since'] . "'";

	}

	if (isset($params['status']) && ($params['status'] !== '')) {

		$statuses = ($params['status'] === 'open') ? pg_return_open_statuses() : array($params['status']);

		$where[] = "status IN ('" . implode("', '", array_map('e', $statuses)) . "')";

	}

	if (isset($params['order_id']) && ((int)$params['order_id'] > 0)) {

		$where[] = "order_id = '" . (int)$params['order_id'] . "'";

	}

	return $where;

}

// Which listing a call asks for: 'newest' (newest id first, as the panel
// lists them, the default) or 'modified' (oldest change first, under
// modified_since, so a client walks forward from its last read).
function api_returns_list_order($params) {

	return isset($params['modified_since']) ? 'modified' : 'newest';

}

// The cursor without its mark, or null when it was made for the other
// listing. The newest-first cursor has no mark (it was handed out before
// modified_since existed); the modified one starts with 'm.', and '.' is not
// in the cursor's alphabet, so neither is read as the other.
function api_returns_cursor_parts($cursor, $order) {

	$cursor = (string)$cursor;

	if ($order === 'modified') {

		return (strpos($cursor, 'm.') === 0) ? (string)substr($cursor, 2) : null;

	}

	return (strpos($cursor, '.') === false) ? $cursor : null;

}

// The cursor of the page after the one ending with $last.
function api_returns_cursor_after($last, $order) {

	if ($order === 'modified') {

		return 'm.' . api_cursor_encode((int)$last['updated_at'], (int)$last['id']);

	}

	return api_cursor_encode(0, (int)$last['id']);

}

// One page of a listing: newest id first (id < the cursor's), or keyset paged
// on (updated_at, id) oldest first under 'modified'. $cursor is
// array('v' => int, 'i' => int) or null.
function api_returns_list_sql($where, $order, $cursor, $limit) {

	if ($order === 'modified') {

		if ($cursor !== null) {

			$value = "'" . (int)$cursor['v'] . "'";

			$where[] = "(updated_at > " . $value . " OR (updated_at = " . $value . " AND id > '" . (int)$cursor['i'] . "'))";

		}

		$sort = "updated_at ASC, id ASC";

	} else {

		if ($cursor !== null) {

			$where[] = "id < '" . (int)$cursor['i'] . "'";

		}

		$sort = "id DESC";

	}

	return "SELECT * FROM order_returns"
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY " . $sort . "
		LIMIT " . ((int)$limit + 1);

}

// GET /returns
function api_returns_list($params) {

	$order = api_returns_list_order($params);

	// The stamp comes with 11.0.168; until the update runs no return can be
	// found by its last change, and an empty list would read as "none".
	if (($order === 'modified') && !pg_schema_has('order_returns', 'updated_at')) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

	$where = api_returns_where($params);

	$cursor = null;

	if (isset($params['cursor']) && ($params['cursor'] !== '')) {

		$parts = api_returns_cursor_parts($params['cursor'], $order);

		$cursor = ($parts === null) ? null : api_cursor_decode($parts);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

	}

	$limit = (int)$params['limit'];

	$rows = pg_returns_ready()
		? api_rows(api_returns_list_sql($where, $order, $cursor, $limit))
		: array();

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$next = api_returns_cursor_after(end($rows), $order);

	}

	// The lines of the page in one query, the deliveries once each.
	$items = pg_return_items_of(array_map(function ($row) { return (int)$row['id']; }, $rows));

	foreach ($rows as $index => $row) {

		$rows[$index]['items'] = isset($items[(int)$row['id']]) ? $items[(int)$row['id']] : array();

	}

	$reasons = pg_return_reasons();

	$windows = api_return_windows_of_orders($rows);

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_order_return_present($row, $reasons, isset($windows[(int)$row['order_id']]) ? $windows[(int)$row['order_id']] : array());

	}

	api_ok_list($out, $limit, $next);

}

// The withdrawal windows of returns of several orders: order id => the map
// api_order_return_windows() makes for that order's returns. A delivery id is
// an id of the order's own ship_tos, so the maps are kept per order.
function api_return_windows_of_orders($rows) {

	$by_order = array();

	foreach ((array)$rows as $row) {

		$by_order[(int)$row['order_id']][] = $row;

	}

	$out = array();

	foreach ($by_order as $order_id => $order_rows) {

		$out[$order_id] = api_order_return_windows($order_rows);

	}

	return $out;

}

// The return of an id, with its lines, or 404.
function api_return_load_or_fail($id) {

	$row = pg_return_load((int)$id);

	if ($row === null) {

		api_fail_not_found(lang('Return request'));

	}

	return $row;

}

// GET /returns/{id}
function api_returns_get($params) {

	$row = api_return_load_or_fail($params['id']);

	api_ok(api_order_return_present($row, null, api_order_return_windows(array($row))));

}

// Whether an action may be made on a return as it stands, and with what:
// every check of the screen and of the write function, before anything is
// written, so a rehearsal answers what the call would. Pure: the caller reads
// the refund plan (pg_return_refund_plan(), only for refund) and the approval
// suggestion (pg_return_suggested_approval() and pg_return_approval_codes(),
// only for approve).
//
// Returns ok, http, code, field, message, to (the status the return moves
// to) and options (what pg_return_staff_move() or pg_return_refund() take).
function api_return_action_check($row, $params, $context = array()) {

	$refuse = function ($http, $code, $message, $field = null) {

		return array('ok' => false, 'http' => $http, 'code' => $code, 'field' => $field, 'message' => $message, 'to' => '', 'options' => array());

	};

	$actions = api_return_actions();

	$action = isset($params['action']) ? (string)$params['action'] : '';

	if (!isset($actions[$action])) {

		return $refuse(422, 'validation_failed', lang('Invalid request.'), 'action');

	}

	$fields = api_return_action_fields();

	foreach (array('note', 'carrier', 'carrier_code', 'tracking_number', 'amount', 'method', 'reference') as $field) {

		if (array_key_exists($field, $params) && !in_array($field, $fields[$action], true)) {

			return $refuse(422, 'validation_failed', lang(array('string' => '{var:1} is not read by the action {var:2}.', 'vars' => array($field, $action))), $field);

		}

	}

	$status = (string)$row['status'];

	// The status the caller last saw, as the screen's forms post it: a move
	// meant for another state is refused rather than made on one the caller
	// never saw.
	if (isset($params['status_was']) && ($params['status_was'] !== '') && !pg_return_status_seen($params['status_was'], $status)) {

		return $refuse(409, 'return_changed', lang('The return was changed in the meantime. Open it again.'), 'status_was');

	}

	$to = $actions[$action];

	$allowed = ($to === 'refunded') ? ($status === 'received') : in_array($to, pg_return_staff_actions($status), true);

	if (!$allowed) {

		return $refuse(409, 'return_not_movable', lang(array(
			'string' => 'A return that is "{var:1}" cannot become "{var:2}".',
			'vars'   => array(pg_return_status_label($status), pg_return_status_label($to))
		)), 'action');

	}

	$note = isset($params['note']) ? (string)$params['note'] : '';

	$options = array();

	switch ($action) {

		case 'reject':

			if (trim($note) === '') {

				return $refuse(422, 'validation_failed', lang('Tell the customer why the return is rejected.'), 'note');

			}

			$options['note'] = $note;

			break;

		case 'approve':

			// What the screen's form starts with; a carrier sent without its
			// code takes that carrier's code from the earlier approvals, as
			// the form swaps an untouched code when the carrier changes.
			$suggested = isset($context['suggested']) ? (array)$context['suggested'] : array('carrier' => '', 'carrier_code' => '');

			$codes = isset($context['codes']) ? (array)$context['codes'] : array();

			$options['note'] = $note;

			if (array_key_exists('carrier', $params)) {

				$carrier = pg_return_carrier($params['carrier']);

				$options['carrier'] = $carrier;

				$options['carrier_code'] = array_key_exists('carrier_code', $params)
					? (string)$params['carrier_code']
					: (isset($codes[$carrier]) ? (string)$codes[$carrier] : '');

			} else {

				$options['carrier'] = (string)$suggested['carrier'];

				$options['carrier_code'] = array_key_exists('carrier_code', $params) ? (string)$params['carrier_code'] : (string)$suggested['carrier_code'];

			}

			break;

		case 'ship':

			$options['tracking_number'] = isset($params['tracking_number']) ? (string)$params['tracking_number'] : '';

			// An empty carrier keeps the one given with the approval.
			if (isset($params['carrier']) && (trim((string)$params['carrier']) !== '')) {

				$options['carrier'] = (string)$params['carrier'];

			}

			break;

		case 'refund':

			$plan = isset($context['plan']) ? $context['plan'] : null;

			if (!is_array($plan)) {

				return $refuse(404, 'not_found', lang('The order could not be found.'));

			}

			// A cancelled order's money went back with the cancellation.
			if (isset($plan['order']['status']) && !pg_return_refund_order_open($plan['order']['status'])) {

				return $refuse(409, 'refund_order_cancelled', lang('The order was cancelled: its money goes back with the cancellation, not with the return.'));

			}

			if (!isset($params['amount'])) {

				return $refuse(422, 'validation_failed', lang('Enter the amount to refund.'), 'amount');

			}

			$method = isset($params['method']) ? (string)$params['method'] : '';

			// Store credit is given on the panel only: the API keeps driver
			// and manual.
			if (!in_array($method, array('driver', 'manual'), true)) {

				return $refuse(422, 'validation_failed', lang('Choose how the money goes back: driver or manual.'), 'method');

			}

			$amount = (int)$params['amount'];

			if (($method === 'driver') && ((string)$plan['driver'] === '')) {

				return $refuse(409, 'refund_driver_unavailable', lang('The payment of this order cannot be refunded from here. Refund it another way and record it.'), 'method');

			}

			if (($amount < 0) || (($method === 'driver') && ($amount === 0))) {

				return $refuse(422, 'validation_failed', lang('The refund amount must be greater than 0.'), 'amount');

			}

			if ($amount > (int)$plan['remaining']) {

				return $refuse(422, 'validation_failed', lang('The refund amount exceeds the remaining refundable amount.'), 'amount');

			}

			$options = array(
				'amount'    => $amount,
				'method'    => $method,
				'reference' => ($method === 'manual') && isset($params['reference']) ? (string)$params['reference'] : ''
			);

			break;

	}

	if ($to !== 'refunded') {

		$options['status_was'] = $status;

	}

	return array('ok' => true, 'http' => 200, 'code' => '', 'field' => null, 'message' => '', 'to' => $to, 'options' => $options);

}

// The answer for a refusal of the write itself, by its code: the checks above
// already ran, so what is left is a race (another move or refund came first,
// the order's return lock was busy) or the payment service's refusal.
function api_return_write_refuse($code, $message) {

	switch ($code) {

		case 'busy':
			api_fail(409, 'return_in_progress', $message);

		case 'refused':
			api_fail(409, 'refund_refused', $message);

		case 'note_required':
			api_fail_validation($message, 'note');

		case 'no_driver':
			api_fail(409, 'refund_driver_unavailable', $message, 'method');

		case 'order_cancelled':
			api_fail(409, 'refund_order_cancelled', $message);

		case 'amount':
		case 'amount_exceeds':
			api_fail_validation($message, 'amount');

		case 'not_installed':
			api_fail(503, 'service_unavailable', $message);

		case 'not_found':
			api_fail_not_found(lang('Return request'));

	}

	api_fail(409, 'return_changed', $message);

}

// POST /returns/{id}
//
// One move, as the return's screen makes it, by the application's owner: the
// decision mail on approve and reject, the refund mail, the audit trail line,
// and order.return.approved, .received and .refunded. A refund through the
// payment service whose answer was unclear keeps the return refunded with
// refund.unclear true, as the screen does; the answer is 200 with that
// return, and staff check the service's own screen.
function api_returns_update($params) {

	$row = api_return_load_or_fail($params['id']);

	$action = (string)$params['action'];

	$context = array();

	if ($action === 'refund') {

		$context['plan'] = ((string)$row['status'] === 'received') ? pg_return_refund_plan($row) : array('driver' => '', 'remaining' => 0);

	}

	if ($action === 'approve') {

		$context['suggested'] = pg_return_suggested_approval($row);

		$context['codes'] = pg_return_approval_codes(pg_return_previous_approvals((int)$row['id']));

	}

	$checked = api_return_action_check($row, $params, $context);

	if (!$checked['ok']) {

		if ($checked['code'] === 'validation_failed') {

			api_fail_validation($checked['message'], $checked['field']);

		}

		if ($checked['code'] === 'not_found') {

			api_fail_not_found(lang('Order'));

		}

		api_fail($checked['http'], $checked['code'], $checked['message'], $checked['field']);

	}

	api_dry_run_stop('updated', 'order_return', array(
		'id'              => (int)$row['id'],
		'action'          => $action,
		'status'          => $checked['to'],
		'previous_status' => (string)$row['status']
	));

	$app = api_current_app();

	$user_id = (int)$app['owner']['id'];

	$log_user = (string)$app['owner']['username'];

	$options = $checked['options'];

	$options['log_user'] = $log_user;

	if ($checked['to'] === 'refunded') {

		$done = pg_return_refund((int)$row['id'], $options, $user_id);

		if (!$done['ok'] && ($done['code'] !== 'unclear')) {

			api_return_write_refuse($done['code'], $done['error']);

		}

	} else {

		$done = pg_return_staff_move((int)$row['id'], $checked['to'], $options, $user_id);

		if (!$done['ok']) {

			api_return_write_refuse($done['code'], $done['error']);

		}

	}

	$after = pg_return_load((int)$row['id']);

	if ($after === null) {

		api_fail_server();

	}

	api_ok(api_order_return_present($after, null, api_order_return_windows(array($after))));

}
