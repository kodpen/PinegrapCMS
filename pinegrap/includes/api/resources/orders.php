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

function api_order_present($row, $items = null) {

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

	return $order;

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

	api_ok(api_order_present($row, $items));

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
