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

if (!defined('PG_API_ENTRY')) {
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
		'offer_code' => $row['special_offer_code']
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
			'tracking_numbers' => isset($numbers[$key]) ? $numbers[$key] : array()
		);

	}

	return $shipments;

}

function api_order_select() {

	return "SELECT orders.id, orders.order_number, orders.order_date, orders.status,
			orders.contact_id,
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
	$item_rows = api_rows("SELECT order_items.id, order_items.product_id, order_items.quantity,
			order_items.price, order_items.tax_total, order_items.shipping,
			order_items.product_name,
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

	static $answer = null;

	if ($answer !== null) {

		return $answer;

	}

	$answer = (api_row("SHOW COLUMNS FROM orders LIKE 'notes'") !== null);

	return $answer;

}

function api_orders_update($params) {

	$id = (int)$params['id'];

	$existing = api_row("SELECT id, order_number, status FROM orders WHERE id = '" . $id . "' LIMIT 1");

	if ($existing === null) {

		api_fail_not_found(lang('Order'));

	}

	$app = api_current_app();

	$set = array();

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
	if (isset($params['status']) && $params['status'] === 'cancelled') {

		if ($existing['status'] !== 'cancelled') {

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

	} elseif (isset($params['status'])) {

		$set[] = "status = '" . escape($params['status']) . "'";

	}

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

	if (empty($set) && !isset($params['status'])) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	if (!empty($set)) {

		api_exec("UPDATE orders SET " . implode(', ', $set) . " WHERE id = '" . $id . "' LIMIT 1");

	}

	log_activity(lang(array(
		'string' => 'Order ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($existing['order_number'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

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

	// The list replaces what the address has, which is what the order screen
	// does with the same field: a shipment that lost a parcel has to be able to
	// go back to one number, and a caller that sends what it knows every time
	// can repeat the call without collecting duplicates.
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

	if (!isset($params['tracking_numbers']) && !isset($params['ship_date']) && !isset($params['delivery_date'])) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	// The carrier is written only into an empty slot. The code beside it is the
	// shipping method the customer chose and paid for; a fulfilment system
	// naming the carrier it happened to use must not overwrite that.
	if (isset($params['carrier'])) {

		$carrier = trim((string)$params['carrier']);

		if (($carrier !== '') && ($recipient['shipping_method_code'] === '')) {

			api_exec("UPDATE ship_tos SET shipping_method_code = '" . escape(mb_substr($carrier, 0, 50)) . "'
				WHERE id = '" . (int)$recipient['id'] . "' LIMIT 1");

		}

	}

	require_once(dirname(__FILE__) . '/../../../update_order.php');

	$outcome = update_order(array('order' => array(
		'id'         => $id,
		'recipients' => array(array(
			'id'               => (int)$recipient['id'],
			// Whether the customer is told. Set either way rather than left
			// out: update_order() reads a missing key as "work it out", and
			// what it works out is that a new number means mail.
			'shipped'          => (isset($params['notify']) && $params['notify']),
			'ship_date'        => $ship_date,
			'delivery_date'    => $delivery_date,
			'tracking_numbers' => $numbers,
			'items'            => array()
		))
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
