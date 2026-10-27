<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Orders, out and back.
//
// The listing exists to be polled: an integration asks for everything since the
// last order it saw and walks forward with a cursor, so it never re-reads the
// whole order history and never skips an order that arrived while it was
// reading. The previous endpoint could only take a limit, capped at five
// hundred, with no way to continue - a shop past that many orders simply could
// not be exported.
//
// The status vocabulary is the store's own, all four of it. The endpoint this
// replaces knew three and had no way to see a cancelled order at all.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(dirname(dirname(__FILE__)) . '/outbound/webhooks.php');

// The store's own vocabulary, spelled the way the column spells it.
//
// It is 'cancelled' with two Ls. That is not a detail: an earlier release had
// one L, a later one redefined the column with two and dropped the first, and
// the rows written in between were coerced to an empty string - a repair
// migration (2026.2.3) had to work out from cancelled_at which orders had
// really been cancelled. Writing the wrong spelling here would start that
// again, quietly, through the API.
function api_order_statuses() {

	return array('incomplete', 'complete', 'exported', 'cancelled');

}

// The money columns, listed once. Every one of them is stored in minor units and
// leaves in minor units - the surface this replaces divided by a hundred here
// and multiplied by a hundred there, so no client could hold one rule.
function api_order_money_fields() {

	return array('subtotal', 'discount', 'tax', 'shipping', 'surcharge', 'total');

}

function api_order_present($row, $items = null, $shipments = null) {

	$order = array(
		'id'           => (int)$row['id'],
		'order_number' => $row['order_number'],
		'status'       => $row['status'],
		'placed_at'      => api_time($row['order_date']),
		'placed_at_unix' => (int)$row['order_date'],
		'customer' => array(
			'contact_id' => ($row['contact_id'] > 0) ? (int)$row['contact_id'] : null,
			'member_id'  => isset($row['member_id']) ? $row['member_id'] : null,
			'first_name' => $row['billing_first_name'],
			'last_name'  => $row['billing_last_name'],
			'email'      => $row['billing_email_address'],
			'company'    => $row['billing_company'],
			'phone'      => $row['billing_phone_number']
		),
		'billing_address' => array(
			'line_1'  => $row['billing_address_1'],
			'line_2'  => $row['billing_address_2'],
			'city'    => $row['billing_city'],
			'state'   => $row['billing_state'],
			'zip'     => $row['billing_zip_code'],
			'country' => $row['billing_country']
		),
		'totals' => array(),
		'payment' => array(
			'method'         => $row['payment_method'],
			'transaction_id' => $row['transaction_id']
		),
		'offer_code' => $row['special_offer_code'],
		// Null on a site that lacks the column (api_order_state_columns()).
		'type'                => array_key_exists('type', $row) ? (string)$row['type'] : null,
		'cancelled_at'        => array_key_exists('cancelled_at', $row) ? api_time($row['cancelled_at']) : null,
		'cancellation_reason' => array_key_exists('cancellation_reason', $row) ? (string)$row['cancellation_reason'] : null,
		'refund_status'       => array_key_exists('refund_status', $row) ? (string)$row['refund_status'] : null
	);

	foreach (api_order_money_fields() as $field) {

		$order['totals'][$field] = api_money($row[$field]);

	}

	if ($items !== null) {

		$order['items'] = $items;

	}

	if ($shipments !== null) {

		$order['shipments'] = $shipments;

	}

	return $order;

}

// A date column, as a date or as nothing.
//
// The shipping columns are DATE and empty is the zero date rather than NULL, so
// a caller handed "0000-00-00" would have to know that story to read a response.
function api_order_date($value) {

	$value = trim((string)$value);

	return (($value === '') || ($value === '0000-00-00')) ? null : $value;

}

// The shipping half of an order.
//
// The store keeps one ship_tos row per address an order goes to - most orders
// have one, a gift order can have several - and the tracking numbers hang off
// that row rather than off the order. So this is a block of its own instead of
// a few more fields beside the billing address, and it is read for one order at
// a time: a listing that carried it would need two more queries per row for
// something the listing does not show.
//
// ship_date is presented for what it is. It is a PLANNED dispatch date on many
// configurations, filled in when the order is placed, which is why the store
// itself does not read it to decide whether an order has gone out
// (_order_has_shipped reads the tracking numbers). A client that treats a date
// here as proof of dispatch would be wrong in the same way.
function api_order_shipments($order_id) {

	$order_id = (int)$order_id;

	$rows = api_rows("SELECT ship_tos.id, ship_tos.ship_to_name, ship_tos.salutation,
			ship_tos.first_name, ship_tos.last_name, ship_tos.company,
			ship_tos.address_1, ship_tos.address_2, ship_tos.city, ship_tos.state,
			ship_tos.zip_code, ship_tos.country, ship_tos.phone_number,
			ship_tos.shipping_cost, ship_tos.shipping_method_id, ship_tos.shipping_method_code,
			ship_tos.arrival_date, ship_tos.ship_date, ship_tos.delivery_date, ship_tos.complete,
			shipping_methods.name AS method_name
		FROM ship_tos
		LEFT JOIN shipping_methods ON shipping_methods.id = ship_tos.shipping_method_id
		WHERE ship_tos.order_id = '" . $order_id . "'
		ORDER BY ship_tos.id ASC");

	if (empty($rows)) {

		return array();

	}

	// One query for the whole order rather than one per recipient: an order with
	// four addresses is not a reason for four round trips.
	$numbers = array();

	foreach (api_rows("SELECT ship_to_id, number FROM shipping_tracking_numbers
			WHERE order_id = '" . $order_id . "' AND TRIM(number) != ''
			ORDER BY id ASC") as $tracking) {

		$key = (int)$tracking['ship_to_id'];

		if (!isset($numbers[$key])) { $numbers[$key] = array(); }

		$numbers[$key][] = $tracking['number'];

	}

	$shipments = array();

	foreach ($rows as $row) {

		$key = (int)$row['id'];

		$shipments[] = array(
			'id'   => $key,
			'name' => $row['ship_to_name'],
			'recipient' => array(
				'salutation' => $row['salutation'],
				'first_name' => $row['first_name'],
				'last_name'  => $row['last_name'],
				'company'    => $row['company'],
				'phone'      => $row['phone_number']
			),
			'address' => array(
				'line_1'  => $row['address_1'],
				'line_2'  => $row['address_2'],
				'city'    => $row['city'],
				'state'   => $row['state'],
				'zip'     => $row['zip_code'],
				'country' => $row['country']
			),
			'method' => array(
				'id'   => ((int)$row['shipping_method_id'] > 0) ? (int)$row['shipping_method_id'] : null,
				'name' => ($row['method_name'] === null) ? null : $row['method_name'],
				'code' => $row['shipping_method_code']
			),
			'shipping'         => api_money($row['shipping_cost']),
			'requested_arrival' => api_order_date($row['arrival_date']),
			'ship_date'        => api_order_date($row['ship_date']),
			'delivery_date'    => api_order_date($row['delivery_date']),
			'complete'         => ((int)$row['complete'] === 1),
			'tracking_numbers' => isset($numbers[$key]) ? $numbers[$key] : array(),
			// Each number with the carrier, link, status and dates the order
			// screen shows for it; tracking_numbers stays for older clients.
			'parcels'          => pg_shipment_api_parcels($key, (string)$row['shipping_method_code'])
		);

	}

	return $shipments;

}

// The order's kind and cancellation state, each added by a later migration
// (type 2026.1.4, the cancellation 2026.1.26, refund_status 2026.1.27): a site
// that missed one still answers, with that field null.
function api_order_state_columns() {

	return array('type', 'cancelled_at', 'cancellation_reason', 'refund_status');

}

// $present lists the state columns the site has; null asks the schema.
function api_order_select($present = null) {

	$state = '';

	foreach (api_order_state_columns() as $column) {

		$has = ($present === null)
			? pg_schema_has('orders', $column)
			: in_array($column, $present, true);

		if ($has) {

			$state .= 'orders.' . $column . ', ';

		}

	}

	return "SELECT orders.id, orders.order_number, orders.order_date, orders.status,
			" . $state . "orders.contact_id,
			orders.billing_first_name, orders.billing_last_name,
			orders.billing_email_address, orders.billing_company,
			orders.billing_address_1, orders.billing_address_2,
			orders.billing_city, orders.billing_state, orders.billing_zip_code,
			orders.billing_country, orders.billing_phone_number,
			orders.subtotal, orders.discount, orders.tax, orders.shipping,
			orders.surcharge, orders.total,
			orders.payment_method, orders.transaction_id, orders.special_offer_code,
			contacts.member_id
		FROM orders
		LEFT JOIN contacts ON orders.contact_id = contacts.id";

}

function api_orders_list($params) {

	$where = array();

	if (isset($params['updated_since'])) {

		$where[] = "orders.order_date >= '" . (int)$params['updated_since'] . "'";

	}

	if (isset($params['date_to'])) {

		$where[] = "orders.order_date <= '" . (int)$params['date_to'] . "'";

	}

	if (isset($params['status'])) {

		$where[] = "orders.status = '" . escape($params['status']) . "'";

	}

	if (isset($params['order_number']) && $params['order_number'] !== '') {

		$where[] = "orders.order_number = '" . escape($params['order_number']) . "'";

	}

	if (isset($params['email']) && $params['email'] !== '') {

		$where[] = "orders.billing_email_address = '" . escape($params['email']) . "'";

	}

	$cursor_clause = '';

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$cursor_clause = "(orders.order_date > '" . $cursor['v'] . "'
			OR (orders.order_date = '" . $cursor['v'] . "' AND orders.id > '" . $cursor['i'] . "'))";

	}

	$paged_where = $where;

	if ($cursor_clause !== '') {

		$paged_where[] = $cursor_clause;

	}

	$where_sql = empty($paged_where) ? '' : ' WHERE ' . implode(' AND ', $paged_where);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	// Oldest first, which is the only order a cursor can walk. A newest-first
	// listing cannot be resumed: every new order shifts the page boundaries.
	$rows = api_rows(api_order_select() . $where_sql . "
		ORDER BY orders.order_date ASC, orders.id ASC
		LIMIT " . ($limit + 1));

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$next_cursor = null;

	if ($has_more && !empty($rows)) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode($last['order_date'], $last['id']);

	}

	$total = null;

	if (!empty($params['include_count'])) {

		$total = (int)api_value("SELECT COUNT(*) FROM orders"
			. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)));

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_order_present($row);

	}

	api_ok_list($out, $limit, $next_cursor, $total);

}

function api_orders_get($params) {

	$id = (int)$params['id'];

	$row = api_row(api_order_select() . " WHERE orders.id = '" . $id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Order'));

	}

	// The lines, with the product id carried through: a marketplace matching an
	// order back to its own catalogue needs the identifier, not only the name
	// the product happened to have when the order was placed.
	//
	// Two names, because they answer different questions. order_items.name is
	// the line's own copy, written when the order was placed and never touched
	// again - it is what the customer bought and what belongs on an invoice.
	// products.name is the catalogue today, which may have been renamed since,
	// and is null when the product has been deleted altogether.
	//
	// ship_to_id says which delivery (shipments[].id) the line went in: a
	// return names lines of one delivery (POST /orders/{id}/returns). null
	// for a line sent to no address, which cannot be returned.
	$item_rows = api_rows("SELECT order_items.id, order_items.product_id, order_items.quantity,
			order_items.price, order_items.tax_total, order_items.shipping,
			order_items.product_name, order_items.ship_to_id,
			products.name AS catalogue_name
		FROM order_items
		LEFT JOIN products ON order_items.product_id = products.id
		WHERE order_items.order_id = '" . $id . "'
		ORDER BY order_items.id ASC");

	$items = array();

	foreach ($item_rows as $item) {

		$items[] = array(
			'id'             => (int)$item['id'],
			'product_id'     => ($item['product_id'] > 0) ? (int)$item['product_id'] : null,
			'name'           => $item['product_name'],
			'catalogue_name' => ($item['catalogue_name'] === null) ? null : $item['catalogue_name'],
			'ship_to_id'     => ((int)$item['ship_to_id'] > 0) ? (int)$item['ship_to_id'] : null,
			'quantity'       => (int)$item['quantity'],
			'price'          => api_money($item['price']),
			// The tax on the whole line, not on one unit. price stays per unit,
			// which is what a caller needs to show a line; multiplying this by
			// the quantity would count it again.
			'tax'            => api_money($item['tax_total']),
			'shipping'       => api_money($item['shipping'])
		);

	}

	api_ok(api_order_present($row, $items, api_order_shipments($id)));

}

// Does this installation keep notes on orders?
//
// Asked once per request and answered from the table itself rather than from a
// version number: the column was added by an upgrade, so its presence does not
// follow from anything else the software can check.
function api_orders_has_notes() {

	return pg_schema_has('orders', 'notes');

}

// What a status change asks of an order, from the status it has ($from) to the
// one sent ($to): 'none', 'set', 'cancel', 'restore' or 'refuse'. The store's
// one rule, pg_order_status_move() (includes/fn/order_status.php), which the
// Workspace's order writer follows too.
function api_orders_status_move($from, $to) {

	return pg_order_status_move($from, $to);

}

function api_orders_update($params) {

	$id = (int)$params['id'];

	$existing = api_row("SELECT id, order_number, status FROM orders WHERE id = '" . $id . "' LIMIT 1");

	if ($existing === null) {

		api_fail_not_found(lang('Order'));

	}

	$app = api_current_app();

	$set = array();

	// Everything the body asks for is judged before anything is carried out.
	//
	// The order matters: cancelling runs the store's own cancellation path,
	// which refunds and mails, and it must not run only for the call to be
	// refused a few lines later over a notes column this installation does not
	// have. It is also what lets a dry run mean something on this endpoint - the
	// checks are above the first write, so X-Dry-Run answers after all of them.
	$move = isset($params['status'])
		? api_orders_status_move($existing['status'], $params['status'])
		: 'none';

	if ($move === 'refuse') {

		api_fail_validation(pg_order_status_move_refusal($existing['status'], $params['status']), 'status');

	}

	$cancelling = ($move === 'cancel');
	$restoring  = ($move === 'restore');

	if (isset($params['notes'])) {

		// orders.notes is not part of the base schema. It arrived with an
		// upgrade path and a good share of installations do not have it, which
		// is why the order screen probes for it too (functions.php, the order
		// view). Writing it blind turned a field an integration is entitled to
		// send into a 500 with nothing in it.
		if (!api_orders_has_notes()) {

			api_fail_validation(
				lang('This installation does not keep notes on orders.'), 'notes');

		}

		$set[] = "notes = '" . escape($params['notes']) . "'";

	}

	if ($move === 'set') {

		$set[] = "status = '" . escape($params['status']) . "'";

	}

	if (empty($set) && !isset($params['status'])) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'order', array(
		'id'        => $id,
		'status'    => isset($params['status']) ? $params['status'] : null,
		'cancelling' => $cancelling,
		'restoring' => $restoring
	));

	$audit_before = pg_audit_fetch('orders', $id);

	// Cancelling is not a status write.
	//
	// The store has one cancellation path, process_order_cancellation(), and it
	// does more than set a column: it refuses an order that has already
	// shipped, records who cancelled it and why, attempts the refund through
	// whichever gateway took the payment, and emails the customer. An API that
	// wrote the column itself would produce an order that is cancelled in the
	// list and unrefunded in the ledger, with the customer never told.
	//
	// It is called as an administrator because an application only reaches this
	// endpoint when its owner deliberately granted orders:write, and the
	// pre-shipment guard exists to stop a CUSTOMER cancelling behind the
	// operator's back - not the operator's own integration.
	if ($cancelling) {

		$reason = isset($params['cancellation_reason'])
			? $params['cancellation_reason']
			: lang(array('string' => 'Cancelled through the API by {var:1}.', 'vars' => $app['name']));

		$outcome = process_order_cancellation($id, $reason, true, (int)$app['owner']['id']);

		if ($outcome['status'] === 'not_found') {

			api_fail_not_found(lang('Order'));

		}

		if ($outcome['status'] === 'shipped') {

			api_fail(409, 'order_already_shipped', lang('This order has already shipped and cannot be cancelled.'));

		}

		if ($outcome['status'] === 'error') {

			api_fail_server();

		}

	}

	// Out of cancelled the order goes the way the order screen's Restore
	// takes it. A plain status write left cancelled_at, the reason and a
	// manual_required refund flag on a completed order, and its gift cards
	// expired. False: another request moved it out of cancelled meanwhile.
	if ($restoring && !pg_order_restore_cancelled($id, $params['status'])) {

		api_fail_validation(lang('Only cancelled orders can be restored.'), 'status');

	}

	if (!empty($set)) {

		api_exec("UPDATE orders SET " . implode(', ', $set) . " WHERE id = '" . $id . "' LIMIT 1");

	}

	log_activity(lang(array(
		'string' => 'Order ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($existing['order_number'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	pg_audit_record($cancelling ? 'cancel' : ($restoring ? 'restore' : 'update'), 'orders', $id, $audit_before);

	$row = api_row(api_order_select() . " WHERE orders.id = '" . $id . "' LIMIT 1");

	// Cancellation announces itself from inside process_order_cancellation(),
	// so only the other status moves are reported here.
	if (isset($params['status']) && $params['status'] !== 'cancelled' && $params['status'] !== $existing['status']) {

		api_webhook_enqueue('order.status_changed', array(
			'id'           => $id,
			'order_number' => $row['order_number'],
			'status'       => $params['status'],
			'previous'     => $existing['status']
		));

	}

	api_ok(api_order_present($row));

}


// Recording that an order went out.
//
// The number is the event, not the date. The store's own answer to "has this
// shipped" is whether a tracking number exists (_order_has_shipped), because
// ship_tos.ship_date is a planned dispatch date on many configurations and is
// filled in when the order is placed. So this endpoint is about the numbers;
// the dates are there for a caller that keeps them.
//
// Written through update_order(), the function the order screen itself posts
// to, rather than into the two tables directly. That function is where the
// rest of the work lives: it removes the numbers this call did not send, marks
// the order modified, writes the activity line and - only when asked - triggers
// the store's "your order has shipped" mail. Writing the tables here would have
// produced a shipment the shop's own screens could disagree with.
//
// The mail is opt-in. An integration backfilling last month's shipments must
// not send a month of notifications to customers who received their parcels
// weeks ago, and an unwanted mail cannot be taken back.
function api_orders_ship($params) {

	$id = (int)$params['id'];

	$order = api_row("SELECT id, order_number, status FROM orders WHERE id = '" . $id . "' LIMIT 1");

	if ($order === null) {

		api_fail_not_found(lang('Order'));

	}

	if ($order['status'] === 'cancelled') {

		api_fail(409, 'order_cancelled',
			lang('This order was cancelled, so a shipment cannot be recorded against it.'));

	}

	$app = api_current_app();

	$recipients = api_rows("SELECT id, ship_date, delivery_date, shipping_method_code
		FROM ship_tos WHERE order_id = '" . $id . "' ORDER BY id ASC");

	if (empty($recipients)) {

		api_fail(409, 'no_shipping_address',
			lang('This order has no shipping address, so there is nothing to ship.'));

	}

	// Which address. One is the normal case and naming it would be ceremony;
	// several is exactly the case where guessing puts the number on the wrong
	// parcel, so there the caller has to say.
	$recipient = null;

	if (isset($params['ship_to_id'])) {

		foreach ($recipients as $row) {

			if ((int)$row['id'] === (int)$params['ship_to_id']) {

				$recipient = $row;

				break;

			}

		}

		if ($recipient === null) {

			api_fail_validation(lang('That shipping address does not belong to this order.'), 'ship_to_id');

		}

	} elseif (count($recipients) === 1) {

		$recipient = $recipients[0];

	} else {

		api_fail_validation(
			lang('This order ships to more than one address: say which one this shipment is for.'), 'ship_to_id');

	}

	// The list, when sent, replaces what the address has, which is what the
	// order screen does with the same field: a shipment that lost a parcel has
	// to be able to go back to one number, and a caller that sends what it
	// knows every time can repeat the call without collecting duplicates. Left
	// out, the numbers are not touched (api_orders_ship_recipient()).
	$numbers = array();

	if (isset($params['tracking_numbers'])) {

		foreach ($params['tracking_numbers'] as $value) {

			if (!is_scalar($value)) {

				api_fail_validation(lang('A tracking number is a single line of text.'), 'tracking_numbers');

			}

			$number = trim((string)$value);

			if ($number === '') {

				continue;

			}

			if (mb_strlen($number) > 100) {

				api_fail_validation(lang('A tracking number cannot be longer than 100 characters.'), 'tracking_numbers');

			}

			if (!in_array($number, $numbers, true)) {

				$numbers[] = $number;

			}

		}

	}

	if (!isset($params['tracking_numbers']) && !isset($params['ship_date']) && !isset($params['delivery_date'])) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	// Above: the order exists, it was not cancelled, the address belongs to it,
	// every tracking number is a line of text of a sane length, and the body
	// asks for something. Below: the first write.
	$request = api_orders_ship_recipient($recipient, $params, $numbers);

	$detail = array(
		'order_id'   => $id,
		'ship_to_id' => (int)$recipient['id']
	);

	// Only a list that was sent replaces anything, and the rehearsal says so
	// the same way.
	if (array_key_exists('tracking_numbers', $request)) {

		$detail['tracking_numbers'] = $request['tracking_numbers'];

	}

	api_dry_run_stop('updated', 'shipment', $detail);

	// The carrier is written only into an empty slot. The code beside it is the
	// shipping method the customer chose and paid for; a fulfilment system
	// naming the carrier it happened to use must not overwrite that. A carrier
	// the store knows also goes on each number sent
	// (api_orders_ship_recipient()), where the parcel keeps it.
	if (isset($params['carrier'])) {

		$carrier = trim((string)$params['carrier']);

		if (($carrier !== '') && ($recipient['shipping_method_code'] === '')) {

			api_exec("UPDATE ship_tos SET shipping_method_code = '" . escape(mb_substr($carrier, 0, 50)) . "'
				WHERE id = '" . (int)$recipient['id'] . "' LIMIT 1");

		}

	}

	require_once(dirname(__FILE__) . '/../../../update_order.php');

	$outcome = update_order(array('source' => 'api', 'order' => array(
		'id'         => $id,
		'recipients' => array($request)
	)));

	if (!is_array($outcome) || !isset($outcome['status']) || ($outcome['status'] !== 'success')) {

		api_fail_server();

	}

	log_activity(lang(array(
		'string' => 'A shipment for order ({var:1}) was recorded through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($order['order_number'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	// order.shipped is announced from inside update_order(), where the order
	// screen's own save passes as well, so the event means the same thing
	// wherever the shipment was recorded.

	$row = api_row(api_order_select() . " WHERE orders.id = '" . $id . "' LIMIT 1");

	api_ok(api_order_present($row, null, api_order_shipments($id)));

}

// The address's part of the update_order() request behind
// POST /orders/{id}/shipment. $recipient is the ship_tos row (id, ship_date,
// delivery_date), $numbers the cleaned list read from tracking_numbers.
//
// tracking_numbers is passed only when the caller sent the field.
// update_order() reads the key as "this is the address's whole list" and
// removes every row not in it, so an empty list built for a call that only
// reports a delivery date would remove the numbers the address has: the order
// would stop counting as shipped (customer cancellation, the unpaid-transfer
// job) and the delivery record kept on those rows would go with them, so the
// next date correction announced order.delivered again. An empty list sent on
// purpose still removes them, as documented.
function api_orders_ship_recipient($recipient, $params, $numbers) {

	// Both dates travel on every call. update_order() writes the pair whenever
	// either one is sent, so sending one alone would blank the other.
	//
	// Read back in the store's own timezone, not UTC. These two columns are
	// calendar days rather than instants, and the value arrives here as a unix
	// timestamp that was parsed in the store's timezone: "2026-01-25" from a
	// shop at UTC+3 is midnight local, which gmdate() would write back as the
	// 24th. A shipment dated the day before the one the caller sent is the kind
	// of thing nobody notices until a courier is asked about the wrong day.
	$ship_date = $recipient['ship_date'];

	$delivery_date = $recipient['delivery_date'];

	if (isset($params['ship_date'])) {

		$ship_date = date('Y-m-d', (int)$params['ship_date']);

	}

	if (isset($params['delivery_date'])) {

		$delivery_date = date('Y-m-d', (int)$params['delivery_date']);

	}

	$request = array(
		'id'            => (int)$recipient['id'],
		// Whether the customer is told. Set either way rather than left out:
		// update_order() reads a missing key as "work it out", and what it
		// works out is that a new number means mail.
		'shipped'       => (isset($params['notify']) && $params['notify']),
		'ship_date'     => $ship_date,
		'delivery_date' => $delivery_date,
		'items'         => array()
	);

	if (isset($params['tracking_numbers'])) {

		$request['tracking_numbers'] = pg_shipment_numbers_with_carrier($numbers,
			isset($params['carrier']) ? (string)$params['carrier'] : '');

	}

	return $request;

}

// The legal documents kept with an order: the pre-information form and the
// distance sales contract the customer accepted at checkout (and, once written,
// the KVKK consent), each as the HTML the customer saw. The copy is the row's
// own: a template edited afterwards changes nothing here, which is the point
// of keeping it - what an integration archives is what was agreed to.
//
// accepted_at is null on a copy that was stored without acceptance (a checkout
// that did not show the box); accepted says the same thing as a boolean so a
// client need not compare timestamps to null.
function api_order_document_present($row, $types = null) {

	if ($types === null) {

		$types = pg_legal_document_types();

	}

	$accepted = (int)$row['accepted_at'];

	return array(
		'id'               => (int)$row['id'],
		'type'             => $row['type'],
		'label'            => isset($types[$row['type']]) ? $types[$row['type']] : null,
		'accepted'         => ($accepted > 0),
		'accepted_at'      => api_time($accepted),
		'accepted_at_unix' => ($accepted > 0) ? $accepted : null,
		'ip'               => (string)$row['ip'],
		'user_agent'       => (string)$row['user_agent'],
		'created_at'       => api_time($row['created_at']),
		'created_at_unix'  => (int)$row['created_at'],
		'html'             => (string)$row['html']
	);

}

// GET /orders/{id}/documents
//
// One page, never more than one row per document type, so there is no cursor.
// An installation upgraded before the legal documents step, or one that never
// switched them on, answers an empty list rather than an error: the order
// exists, it simply has no documents. The switch being off today does not hide
// what was kept while it was on.
function api_orders_documents($params) {

	$id = (int)$params['id'];

	$order = api_row("SELECT id FROM orders WHERE id = '" . $id . "' LIMIT 1");

	if ($order === null) {

		api_fail_not_found(lang('Order'));

	}

	$out = array();

	if (pg_schema_has('order_documents')) {

		$types = pg_legal_document_types();

		foreach (api_rows("SELECT id, type, html, accepted_at, ip, user_agent, created_at
			FROM order_documents
			WHERE order_id = '" . $id . "'
			ORDER BY id ASC") as $row) {

			$out[] = api_order_document_present($row, $types);

		}

	}

	api_ok_list($out, max(1, count($out)), null);

}

// What api_order_document_present() returns, declared for the OpenAPI document.
function api_order_document_schema() {

	return array(
		'id'               => 'integer',
		'type'             => 'string',
		'label'            => 'string?',
		'accepted'         => 'boolean',
		'accepted_at'      => 'string?',
		'accepted_at_unix' => 'integer?',
		'ip'               => 'string',
		'user_agent'       => 'string',
		'created_at'       => 'string?',
		'created_at_unix'  => 'integer',
		'html'             => 'string'
	);

}

// A customer return (includes/fn/returns.php): one request to send back goods
// of one delivery of the order, as pg_return_load() and pg_returns_of_order()
// read it. The lines are the order's own line ids and how many of each go
// back; their names are in GET /orders/{id}.
//
// Each status reached keeps its own time, null until it is reached. decided_at
// is the approval's or the rejection's. carrier is null until staff named the
// carrier the goods go back with (a key such as yurtici when the site knows
// it, otherwise the text given). reason_label is null for a code this version
// has no name for. refund is api_order_return_refund_present()'s, withdrawal
// api_order_return_withdrawal_present()'s, from $windows: the deliveries'
// withdrawal windows, prepared once per request by api_order_return_windows().
function api_order_return_present($row, $reasons = null, $windows = array()) {

	if ($reasons === null) {

		$reasons = pg_return_reasons();

	}

	$items = array();

	foreach ((array)(isset($row['items']) ? $row['items'] : array()) as $item) {

		$items[] = array(
			'order_item_id' => (int)$item['order_item_id'],
			'quantity'      => (int)$item['quantity']
		);

	}

	$reason = (string)$row['reason'];

	return array(
		'id'              => (int)$row['id'],
		'order_id'        => (int)$row['order_id'],
		'ship_to_id'      => (int)$row['ship_to_id'],
		'status'          => (string)$row['status'],
		'status_label'    => pg_return_status_label((string)$row['status']),
		'reason'          => $reason,
		'reason_label'    => isset($reasons[$reason]) ? $reasons[$reason] : null,
		'customer_note'   => (string)$row['customer_note'],
		'decision_note'   => (string)$row['decision_note'],
		'source'          => (string)$row['source'],
		'carrier'         => ((string)$row['carrier'] !== '') ? (string)$row['carrier'] : null,
		'carrier_code'    => (string)$row['carrier_code'],
		'tracking_number' => (string)$row['tracking_number'],
		'items'           => $items,
		'requested_at'    => api_time($row['requested_at']),
		'decided_at'      => api_time($row['decided_at']),
		'shipped_at'      => api_time($row['shipped_at']),
		'received_at'     => api_time($row['received_at']),
		'refunded_at'     => api_time($row['refunded_at']),
		'closed_at'       => api_time($row['closed_at']),
		'refund'          => api_order_return_refund_present($row),
		'withdrawal'      => api_order_return_withdrawal_present($row, $windows)
	);

}

// Whether a return came in time, as the panel's side column shows it
// (pg_return_timing()): the day its delivery was delivered, or is taken to
// have been (delivered_estimated: worked out from the ship date or the
// expected delivery date), the last day of the withdrawal period, and whether
// the request was made by then. Read from the delivery as it is known today,
// so a delivery date learned later moves the deadline and can change the
// answer. null when no date is known for the delivery, when the delivery is
// gone, or when $windows was not prepared for it.
function api_order_return_withdrawal_present($row, $windows) {

	$ship_to_id = (int)$row['ship_to_id'];

	$window = (is_array($windows) && isset($windows[$ship_to_id])) ? $windows[$ship_to_id] : null;

	$requested_day = ((int)$row['requested_at'] > 0) ? date('Y-m-d', (int)$row['requested_at']) : '';

	$timing = pg_return_timing($window, $requested_day);

	if ($timing === null) {

		return null;

	}

	return array(
		'delivered_on'        => $timing['delivered'],
		'delivered_estimated' => $timing['estimated'],
		'deadline'            => $timing['deadline'],
		'requested_in_time'   => $timing['in_time']
	);

}

// The withdrawal window of each delivery the returns name, read once per
// delivery (pg_return_delivery_state() as of today), so a page of an order's
// returns asks for each delivery once. ship_to_id => pg_return_window(), or
// null when the delivery has no date or is gone.
function api_order_return_windows($rows) {

	$windows = array();

	foreach ((array)$rows as $row) {

		$ship_to_id = (int)$row['ship_to_id'];

		if (array_key_exists($ship_to_id, $windows)) {

			continue;

		}

		$state = pg_return_delivery_state((int)$row['order_id'], $ship_to_id);

		$windows[$ship_to_id] = is_array($state) ? $state['window'] : null;

	}

	return $windows;

}

// The refund a return keeps (order_returns.refund_*, 11.0.34): the amount,
// method and unclear the order.return.refunded event carries (as amount,
// refund_method and unclear), and the reference, which the event does not
// carry. null until the return was refunded, and on a site whose upgrade
// has not added the columns yet; a return closed after its refund keeps
// it. amount is in minor units (0 is a refund recorded as 0). method is
// driver (through the payment service) or manual (made another way and
// recorded). Both are null for a return refunded before the columns whose
// refund could not be found again. reference is the service's refund id or
// the typed reference, null when none. unclear: the service gave no clear
// answer, so the refund is booked but unconfirmed; it is also true while
// the service is being asked.
function api_order_return_refund_present($row) {

	if (((int)$row['refunded_at'] <= 0) || !array_key_exists('refund_method', $row)) {

		return null;

	}

	$method = (string)$row['refund_method'];

	$known = in_array($method, array('driver', 'manual'), true);

	$reference = isset($row['refund_reference']) ? (string)$row['refund_reference'] : '';

	return array(
		'amount'    => $known ? (int)(isset($row['refund_amount']) ? $row['refund_amount'] : 0) : null,
		'method'    => $known ? $method : null,
		'reference' => ($reference !== '') ? $reference : null,
		'unclear'   => ((int)(isset($row['refund_unclear']) ? $row['refund_unclear'] : 0) === 1)
	);

}

// GET /orders/{id}/returns
//
// One page, newest first: an order has a handful of returns at most, so there
// is no cursor. A site not yet updated to the version that keeps returns
// answers an empty list, as an order without returns does
// (pg_returns_of_order() reads nothing before the tables are there).
function api_orders_returns($params) {

	$id = (int)$params['id'];

	$order = api_row("SELECT id FROM orders WHERE id = '" . $id . "' LIMIT 1");

	if ($order === null) {

		api_fail_not_found(lang('Order'));

	}

	$reasons = pg_return_reasons();

	$rows = pg_returns_of_order($id);

	$windows = api_order_return_windows($rows);

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_order_return_present($row, $reasons, $windows);

	}

	api_ok_list($out, max(1, count($out)), null);

}

// The lines POST /orders/{id}/returns names, as pg_return_create() takes them:
// order item id => quantity. Only the shape is judged here - an object with a
// whole order_item_id of 1 or more and a single quantity, each line once; the
// quantity itself, and whether the line is the delivery's, are the return
// model's to judge (pg_return_check_lines()), so the API and My Account meet
// one set of refusals. An empty list is the model's too.
//
// Returns ok, error and quantities.
function api_order_returns_lines($items) {

	$refuse = function ($error) {

		return array('ok' => false, 'error' => $error, 'quantities' => array());

	};

	$quantities = array();

	foreach ((array)$items as $item) {

		if (!is_array($item) || !isset($item['order_item_id']) || !array_key_exists('quantity', $item)
			|| !is_scalar($item['order_item_id']) || !(is_scalar($item['quantity']) || ($item['quantity'] === null))) {

			return $refuse(lang('Each item is an object of order_item_id and quantity.'));

		}

		$line = $item['order_item_id'];

		if (!(is_int($line) || (is_string($line) && ctype_digit($line))) || ((int)$line < 1)) {

			return $refuse(lang('order_item_id is the id of one of the order\'s lines.'));

		}

		// array_key_exists(): a quantity left null is stored as null, and the
		// line is named all the same.
		if (array_key_exists((int)$line, $quantities)) {

			return $refuse(lang('Name each order line once.'));

		}

		$quantities[(int)$line] = $item['quantity'];

	}

	return array('ok' => true, 'error' => '', 'quantities' => $quantities);

}

// Answers a refusal of pg_return_create_check() or pg_return_create(), by its
// code and field rather than by its sentence. $ship_to says how the delivery
// was chosen: sent (the caller named it), one (the order's only one), several
// or none (none was named and the order's goods went to several addresses, or
// to none). Never returns.
function api_orders_returns_refuse($result, $ship_to) {

	$code = (string)$result['code'];

	if ($code === 'not_installed') {

		api_fail(503, 'service_unavailable', $result['error']);

	}

	if ($code === 'order_missing') {

		api_fail_not_found(lang('Order'));

	}

	if ($code === 'order_not_returnable') {

		api_fail(409, 'order_not_returnable', $result['error']);

	}

	if ($code === 'busy') {

		api_fail(409, 'return_in_progress', $result['error']);

	}

	// The customer's timing rules, checked only with customer_rules.
	if ($code === 'not_sent') {

		api_fail(409, 'not_sent', $result['error']);

	}

	if ($code === 'delivery_date_unknown') {

		api_fail(409, 'delivery_date_unknown', $result['error']);

	}

	if ($code === 'window_closed') {

		api_fail(409, 'return_period_ended', $result['error']);

	}

	// No delivery was named and none could be taken for granted.
	if (($code === 'delivery_not_in_order') && ($ship_to === 'several')) {

		api_fail_validation(lang('This order\'s goods went to more than one address: say which delivery the return is for.'), 'ship_to_id');

	}

	if (($code === 'delivery_not_in_order') && ($ship_to === 'none')) {

		api_fail(409, 'no_shipping_address', lang('None of this order\'s goods went to an address, so there is nothing to send back.'));

	}

	api_fail_validation($result['error'], ($result['field'] !== '') ? $result['field'] : null);

}

// POST /orders/{id}/returns
//
// Recorded the way My Account records a customer's request
// (pg_return_create()), with source api and the application's owner as the
// person who asked. By default a request recorded for the shop skips the
// customer's timing rules, as a staff member's does: the withdrawal period,
// that the goods have left, that a delivery date is known. Staff decide on
// the panel, which shows whether the request came in time (and so does the
// answer's withdrawal); a delivery not yet sent is normally cancelled
// instead. An app acting for the customer sends customer_rules and is held
// to them, refused with 409 not_sent, delivery_date_unknown or
// return_period_ended. The checks are the model's, run once before the
// rehearsal stops (pg_return_create_check()) and again by pg_return_create()
// itself, which checks the lines once more under the order's lock.
//
// A new request rings the staff's bell and writes an activity line, as one
// from My Account does. Unlike My Account it also writes an audit line (the
// API's writes are audited), and it mails the customer only when notify is
// true, because a request a shop records for its customer may have been
// agreed with them already; My Account always mails. The webhook comes from
// pg_return_create().
// The same request sent again within PG_RETURN_REPEAT_SECONDS (a retry after
// a timeout) answers 200 with the return it repeats and does nothing else.
function api_orders_returns_create($params) {

	$id = (int)$params['id'];

	$order = api_row("SELECT id, order_number FROM orders WHERE id = '" . $id . "' LIMIT 1");

	if ($order === null) {

		api_fail_not_found(lang('Order'));

	}

	$app = api_current_app();

	$lines = api_order_returns_lines($params['items']);

	if (!$lines['ok']) {

		api_fail_validation($lines['error'], 'items');

	}

	// Which delivery. One is the normal case and naming it would be ceremony;
	// several is exactly the case where guessing sends back the wrong parcel,
	// so there the caller has to say. The deliveries are the ones goods can be
	// returned from, as the return model reads them: lines sent to an address
	// and not kept aside in the cart.
	if (isset($params['ship_to_id'])) {

		$ship_to_id = (int)$params['ship_to_id'];

		$ship_to = 'sent';

	} else {

		$deliveries = api_rows("SELECT DISTINCT ship_to_id FROM order_items
			WHERE (order_id = '" . $id . "') AND (ship_to_id > 0) AND (saved_for_later = 0)
			ORDER BY ship_to_id ASC");

		$ship_to_id = (count($deliveries) === 1) ? (int)$deliveries[0]['ship_to_id'] : 0;

		$ship_to = (count($deliveries) === 1) ? 'one' : (empty($deliveries) ? 'none' : 'several');

	}

	$note = isset($params['note']) ? $params['note'] : '';

	// customer_rules holds the request to the customer's timing rules, for an
	// app acting for the customer; the source stays api, so the repeat is
	// still looked for among this app owner's requests.
	$options = array(
		'source'         => 'api',
		'user_id'        => (int)$app['owner']['id'],
		'customer_rules' => (isset($params['customer_rules']) && $params['customer_rules'])
	);

	$checked = pg_return_create_check($id, $ship_to_id, $lines['quantities'], $params['reason'], $note, $options);

	if (!$checked['ok']) {

		api_orders_returns_refuse($checked, $ship_to);

	}

	// The same request again: nothing is written, so a rehearsal gets the same
	// answer as the call.
	if ($checked['repeat'] > 0) {

		$repeated = pg_return_load($checked['repeat']);

		if ($repeated !== null) {

			api_ok(api_order_return_present($repeated, null, api_order_return_windows(array($repeated))));

		}

	}

	$detail = array(
		'order_id'   => $id,
		'ship_to_id' => $ship_to_id,
		'reason'     => $checked['reason'],
		'items'      => array()
	);

	foreach ($checked['lines'] as $item_id => $quantity) {

		$detail['items'][] = array('order_item_id' => (int)$item_id, 'quantity' => (int)$quantity);

	}

	api_dry_run_stop('created', 'order_return', $detail);

	$created = pg_return_create($id, $ship_to_id, $lines['quantities'], $params['reason'], $note, $options);

	if (!$created['ok']) {

		api_orders_returns_refuse($created, $ship_to);

	}

	$row = pg_return_load($created['id']);

	if ($row === null) {

		api_fail_server();

	}

	if ($created['repeat']) {

		api_ok(api_order_return_present($row, null, api_order_return_windows(array($row))));

	}

	// The request stands whatever happens to the bell, the log or the mail.
	pg_return_notify_staff($created['id'], 'requested');

	log_activity(lang(array(
		'string' => 'Return request {var:1} was recorded for order {var:2} through the API by the application {var:3} (key {var:4}).',
		'vars'   => array($created['id'], $order['order_number'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	pg_audit('create', 'order_return', (int)$created['id'], array(), pg_return_audit_create_fields($row),
		lang(array('string' => 'Return {var:1} of order {var:2}', 'vars' => array((int)$created['id'], $order['order_number']))));

	if (isset($params['notify']) && $params['notify']) {

		pg_return_send_request_mail($created['id']);

	}

	api_ok(api_order_return_present($row, null, api_order_return_windows(array($row))), 201);

}

// What api_order_return_present() returns, declared for the OpenAPI document.
function api_order_return_schema() {

	return array(
		'id'              => 'integer',
		'order_id'        => 'integer',
		'ship_to_id'      => 'integer',
		'status'          => 'string',
		'status_label'    => 'string',
		'reason'          => 'string',
		'reason_label'    => 'string?',
		'customer_note'   => 'string',
		'decision_note'   => 'string',
		'source'          => 'string',
		'carrier'         => 'string?',
		'carrier_code'    => 'string',
		'tracking_number' => 'string',
		'items'           => array(array(
			'order_item_id' => 'integer',
			'quantity'      => 'integer'
		)),
		'requested_at'    => 'string',
		'decided_at'      => 'string?',
		'shipped_at'      => 'string?',
		'received_at'     => 'string?',
		'refunded_at'     => 'string?',
		'closed_at'       => 'string?',
		'refund'          => 'OrderReturnRefund?',
		'withdrawal'      => 'OrderReturnWithdrawal?'
	);

}

// What api_order_return_withdrawal_present() returns, declared for the
// OpenAPI document.
function api_order_return_withdrawal_schema() {

	return array(
		'delivered_on'        => 'string',
		'delivered_estimated' => 'boolean',
		'deadline'            => 'string',
		'requested_in_time'   => 'boolean'
	);

}

// What api_order_return_refund_present() returns, declared for the OpenAPI
// document.
function api_order_return_refund_schema() {

	return array(
		'amount'    => 'integer?',
		'method'    => 'string?',
		'reference' => 'string?',
		'unclear'   => 'boolean'
	);

}

// What api_order_present() returns, declared for the OpenAPI document. items
// and shipments are carried by the single-order endpoint only.
function api_order_schema() {

	return array(
		'id'             => 'integer',
		'order_number'   => 'string?',
		'status'         => 'string',
		'placed_at'      => 'string?',
		'placed_at_unix' => 'integer',
		'customer' => array(
			'contact_id' => 'integer?',
			'member_id'  => 'string?',
			'first_name' => 'string?',
			'last_name'  => 'string?',
			'email'      => 'string?',
			'company'    => 'string?',
			'phone'      => 'string?'
		),
		'billing_address' => array(
			'line_1'  => 'string?',
			'line_2'  => 'string?',
			'city'    => 'string?',
			'state'   => 'string?',
			'zip'     => 'string?',
			'country' => 'string?'
		),
		'totals' => array(
			'subtotal'  => 'integer',
			'discount'  => 'integer',
			'tax'       => 'integer',
			'shipping'  => 'integer',
			'surcharge' => 'integer',
			'total'     => 'integer'
		),
		'payment' => array(
			'method'         => 'string',
			'transaction_id' => 'string?'
		),
		'offer_code' => 'string',
		'type'                => 'string?',
		'cancelled_at'        => 'string?',
		'cancellation_reason' => 'string?',
		'refund_status'       => 'string?',
		'items' => array(array(
			'id'             => 'integer',
			'product_id'     => 'integer?',
			'name'           => 'string',
			'catalogue_name' => 'string?',
			'ship_to_id'     => 'integer?',
			'quantity'       => 'integer',
			'price'          => 'integer',
			'tax'            => 'integer',
			'shipping'       => 'integer'
		)),
		'shipments' => array(array(
			'id'   => 'integer',
			'name' => 'string',
			'recipient' => array(
				'salutation' => 'string',
				'first_name' => 'string',
				'last_name'  => 'string',
				'company'    => 'string',
				'phone'      => 'string'
			),
			'address' => array(
				'line_1'  => 'string',
				'line_2'  => 'string',
				'city'    => 'string',
				'state'   => 'string',
				'zip'     => 'string',
				'country' => 'string'
			),
			'method' => array(
				'id'   => 'integer?',
				'name' => 'string?',
				'code' => 'string'
			),
			'shipping'          => 'integer',
			'requested_arrival' => 'string?',
			'ship_date'         => 'string?',
			'delivery_date'     => 'string?',
			'complete'          => 'boolean',
			'tracking_numbers'  => 'string[]',
			'parcels' => array(array(
				'number'       => 'string',
				'carrier'      => 'string?',
				'carrier_name' => 'string?',
				'tracking_url' => 'string?',
				'status'       => 'string',
				'shipped_at'   => 'string?',
				'delivered_at' => 'string?'
			))
		))
	);

}
