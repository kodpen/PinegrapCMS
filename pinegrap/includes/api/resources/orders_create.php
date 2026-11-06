<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// POST /orders: a local (counter) sale, the order add_order.php completes.
// The lines are checked the way the counter checks its cart, then written as
// an incomplete order and completed by pg_local_order_complete()
// (includes/fn/local_order.php), the screen's own completion: the claim, the
// number, the stock, order.created and, with the ERP on, the invoice and the
// money. The owner of the application is the operator: the ERP rights on
// their account decide whether the money is recorded, as at the till.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The ways of paying the route takes; the counter's local_sale_methods().
function api_local_sale_methods() {

	return array('cash', 'card', 'transfer', 'cheque', 'other', 'later', 'split');

}

// payment_parts as the counter's split payment reads them
// (local_sale_split_parts()): {method, till, amount}. bad is the number of
// the first item (from 1) that is not an object with a whole, non-negative
// number of minor units as its amount, 0 when none is: the counter skips a
// part without an amount as empty, so such a part sent here would vanish and
// the money it stood for would be recorded as owed. A form body sends the
// amount as digits, which are taken.
function api_local_sale_parts($raw) {

	$parts = array();

	foreach (array_values((array)$raw) as $index => $part) {

		$amount = (is_array($part) && isset($part['amount'])) ? $part['amount'] : null;

		if (!is_int($amount) && !(is_string($amount) && preg_match('/^\d{1,10}$/', trim($amount)))) {

			return array('parts' => array(), 'bad' => $index + 1);

		}

		if ((int)$amount < 0) {

			return array('parts' => array(), 'bad' => $index + 1);

		}

		$parts[] = array(
			'method' => isset($part['method']) ? (string)$part['method'] : '',
			'till'   => isset($part['till_id']) ? (int)$part['till_id'] : 0,
			'amount' => (int)$amount
		);

	}

	return array('parts' => $parts, 'bad' => 0);

}

// A refusal of pg_local_order_check() or pg_local_order_complete() as a 422:
// a line's reason names its product, the cash shortfall points at received,
// the split payment's checks at payment_parts. $product_ids maps the line ids
// the handler gave its lines back to the products.
function api_local_sale_refuse($result, $product_ids, $method) {

	if (!empty($result['line_errors'])) {

		$line_id = (int)key($result['line_errors']);

		api_fail_validation(lang(array(
			'string' => 'Product {var:1}: {var:2}',
			'vars'   => array(isset($product_ids[$line_id]) ? $product_ids[$line_id] : $line_id, (string)current($result['line_errors']))
		)), 'items');

	}

	if ($result['field'] === 'received') {

		api_fail_validation($result['message'], 'received');

	}

	api_fail_validation($result['message'], ($method === 'split') ? 'payment_parts' : 'items');

}

function api_orders_create($params) {

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/local_order.php');

	$app = api_current_app();
	$user = $app['owner'];

	// 1. The lines.
	$read = pg_local_order_items_input(isset($params['items']) ? $params['items'] : null);

	if ($read['error'] === 'quantity') {

		api_fail_validation(lang('The quantity must be a whole number from 1 to 9999.'), 'items');

	}

	if ($read['error'] !== '') {

		api_fail_validation(lang('items must list objects of {product_id, quantity} with whole numbers.'), 'items');

	}

	$ids = array_keys($read['items']);
	$found = array();

	foreach (api_rows("SELECT id, name, short_description, price, selection_type, enabled,
			inventory, inventory_quantity, taxable, tax_rate
		FROM products
		WHERE id IN ('" . implode("', '", array_map('intval', $ids)) . "')") as $row) {

		$found[(int)$row['id']] = $row;

	}

	$rows = array();
	$items = array();
	$product_ids = array();

	foreach ($read['items'] as $product_id => $quantity) {

		// The counter adds only a product that is switched on (add_order_item()).
		if (!isset($found[$product_id]) || ((int)$found[$product_id]['enabled'] !== 1)) {

			api_fail_validation(lang(array('string' => 'Product {var:1} was not found or is switched off.', 'vars' => array($product_id))), 'items');

		}

		$product = $found[$product_id];

		// A donation enters the cart with the amount given, none at the counter.
		$price = ($product['selection_type'] === 'donation') ? 0 : (int)$product['price'];

		$line_id = count($rows) + 1;
		$product_ids[$line_id] = $product_id;

		$rows[] = array_merge($product, array('id' => $line_id, 'product_id' => $product_id, 'quantity' => $quantity, 'price' => $price));
		$items[$product_id] = array('quantity' => $quantity, 'name' => (string)$product['name'], 'price' => $price);

	}

	// 2. The customer: nobody (a walk-in sale), a contact, or an ERP account.
	$customer = null;
	$contact_id = isset($params['contact_id']) ? (int)$params['contact_id'] : 0;
	$account_id = isset($params['account_id']) ? (int)$params['account_id'] : 0;

	if (($contact_id > 0) && ($account_id > 0)) {

		api_fail_validation(lang('Send contact_id or account_id, not both.'), 'account_id');

	}

	if (($contact_id > 0) || ($account_id > 0)) {

		$customer = pg_local_order_customer($contact_id, $account_id);

		if ($customer === null) {

			api_fail_validation(lang('That customer could not be found.'), ($contact_id > 0) ? 'contact_id' : 'account_id');

		}

	}

	// 3. The payment and every check of the completion.
	$method = isset($params['payment_method']) ? $params['payment_method'] : 'later';

	if (($method === 'split') !== isset($params['payment_parts'])) {

		api_fail_validation(lang('payment_parts goes with payment_method split, and only with it.'), 'payment_parts');

	}

	$parts = api_local_sale_parts(isset($params['payment_parts']) ? $params['payment_parts'] : array());

	if ($parts['bad'] > 0) {

		api_fail_validation(lang(array(
			'string' => 'payment_parts item {var:1}: send an object of {method, till_id, amount}, the amount a whole number of minor units.',
			'vars'   => $parts['bad']
		)), 'payment_parts');

	}

	$input = array(
		'pay_method' => $method,
		'pay_till'   => isset($params['till_id']) ? (int)$params['till_id'] : 0,
		'received'   => isset($params['received']) ? (int)$params['received'] : 0,
		'pay_parts'  => $parts['parts']
	);

	$lines = pg_local_order_lines($rows);
	$checked = pg_local_order_check($user, 0, $lines, $input);

	if (!$checked['ok']) {

		api_local_sale_refuse($checked, $product_ids, $method);

	}

	$totals = local_sale_totals($lines);

	api_dry_run_stop('created', 'order', array(
		'lines'    => count($lines),
		'units'    => (int)$totals['units'],
		'total'    => api_money($totals['gross']),
		'customer' => ($customer === null) ? null : $customer['label'],
		'invoice'  => local_sale_erp_on()
	));

	// 4. Written, then completed. A completion refused on the way (the stock
	// sold by a till meanwhile) leaves no basket behind.
	$order_id = pg_local_order_open((int)$user['id'], $items);

	if ($order_id <= 0) {

		api_fail_server();

	}

	$done = pg_local_order_complete($user, $order_id, $customer, $input);

	if (!$done['ok']) {

		pg_local_order_discard($order_id);

		api_local_sale_refuse($done, $product_ids, $method);

	}

	$result = $done['result'];

	log_activity(lang(array(
		'string' => 'Order ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($result['order_number'], $app['name'], $app['api_key'])
	)), $user['username']);

	pg_audit_record('create', 'orders', $order_id);

	api_ok(api_local_sale_present($result, api_order_full($order_id)), 201);

}

// What POST /orders answers: the order as GET /orders/{id} gives it and what
// the completion did beside it.
function api_local_sale_present($result, $order) {

	$receipts = array();

	if (!empty($result['receipt'])) {

		$receipts[] = array('id' => (int)$result['receipt']['id'], 'till' => (string)$result['receipt']['till'], 'method' => (string)$result['method'], 'amount' => api_money($result['total']));

	}

	foreach ($result['receipts'] as $receipt) {

		$receipts[] = array('id' => (int)$receipt['id'], 'till' => (string)$receipt['till'], 'method' => (string)$receipt['method'], 'amount' => api_money($receipt['amount']));

	}

	return array(
		'order'    => $order,
		'invoice'  => empty($result['invoice']) ? null : array('id' => (int)$result['invoice']['id'], 'number' => (string)$result['invoice']['number']),
		'receipts' => $receipts,
		'warnings' => array_values($result['warnings'])
	);

}

// What api_local_sale_present() returns.
function api_local_sale_schema() {

	return array(
		'order'    => 'Order',
		'invoice'  => 'LocalSaleInvoice?',
		'receipts' => 'LocalSaleReceipt[]',
		'warnings' => 'string[]'
	);

}

function api_local_sale_invoice_schema() {

	return array(
		'id'     => 'integer',
		'number' => 'string'
	);

}

function api_local_sale_receipt_schema() {

	return array(
		'id'     => 'integer',
		'till'   => 'string',
		'method' => 'string',
		'amount' => 'integer'
	);

}
