<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Bringing an order in from a marketplace.
//
// Writing an order is normally the checkout's job, and the checkout is seven
// thousand lines that assume a session, a cart, a browser and a payment
// gateway. None of that exists here: this runs from cron, with no session and
// no logged-in user, and the sale already happened somewhere else and was
// already paid for. So the three rows are written directly rather than through
// initialize_order() and add_order_item(), which read the cart out of
// $_SESSION and would have to be lied to.
//
// What is deliberately NOT done, and why. The checkout fires about thirty side
// effects; almost none of them belong to a sale that was made on somebody
// else's website:
//
//   No order receipt e-mail. The marketplace has already sent one, and it is
//   the marketplace's customer. A second receipt from a shop the buyer did not
//   think they were buying from is at best confusing.
//
//   No auto-registration, no reward points, no gift card issuance, no e-mail
//   campaigns, no offers. All of them are storefront mechanics, and the buyer
//   never saw the storefront. Offers in particular would re-price the order
//   against rules the marketplace knew nothing about, and the marketplace's
//   price is the price that was actually charged.
//
//   No payment. There is nothing to charge - the money is the marketplace's to
//   collect and to settle.
//
// What IS done: the stock comes off, which is the whole point of importing the
// order at all, and that in turn queues the new quantity back out to every
// other marketplace this product is listed on. The operator is notified the
// same way a web order notifies them, because an order that arrives silently is
// an order that ships late.

if (!defined('PG_INIT_LOADED')) {

	exit;

}

require_once(dirname(__FILE__) . '/connectors/base.php');

// Import one package. Answers array('ok', 'order_id', 'error', 'skipped').
//
// $package is the normalised shape a connector answers with:
//
//   remote_order_id    their order number, shown to the customer
//   remote_package_id  the unit that ships, and the unit this is keyed on
//   remote_status      their word for where it is
//   placed_at          unix seconds
//   customer           array(first_name, last_name, email, phone, company)
//   billing            array(address_1, address_2, city, state, zip, country)
//   shipping_address   the same shape, or an empty array
//   currency           three letters
//   lines              array of array(remote_line_id, code, name, quantity,
//                      price, tax, discount)
//
// price, tax and discount are minor units PER UNIT, because that is what
// order_items stores and converting once here is better than three connectors
// each getting it wrong differently.
function mp_import_order($account, $package) {

	$account_id = (int) $account['id'];

	$package_id = isset($package['remote_package_id']) ? trim((string)$package['remote_package_id']) : '';

	if ($package_id === '') {

		return array('ok' => false, 'order_id' => 0, 'skipped' => false,
			'error' => lang('The marketplace sent an order with no package number.'));

	}

	// Claimed before anything is written.
	//
	// The unique key on (account_id, remote_package_id) is what makes this
	// idempotent: there are no transactions on these tables, so the only
	// protection against importing an order twice is a row that already exists
	// saying somebody is importing it. A crash after this point leaves a row in
	// 'importing' with no order, which the next pass reports rather than
	// silently repeating.
	$existing = db_item("SELECT id, order_id, status FROM marketplace_order_map
		WHERE (account_id = '" . e($account_id) . "') AND (remote_package_id = '" . e($package_id) . "')");

	if ($existing) {

		// Already here, so the order is not written again - but what has
		// happened to it since is. The feed is queried on the modification
		// time, so a package that shipped, arrived or was cancelled comes back
		// through this path, and it is the only chance the shop has to hear
		// about it.
		db("UPDATE marketplace_order_map
			SET remote_status = '" . e(mb_substr((string)$package['remote_status'], 0, 40)) . "',
				updated_timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . e((int)$existing['id']) . "'");

		if ((int)$existing['order_id']) {

			mp_order_apply_status($account, $package, $existing);

		}

		return array('ok' => true, 'order_id' => (int)$existing['order_id'], 'skipped' => true, 'error' => '');

	}

	// Written through mysqli_query() rather than db(): here a duplicate key is
	// not a failure but the answer, and db() ends the process on any failed
	// statement, so the lost race could never have been reported. The unique
	// key on (account_id, remote_package_id) refuses the second writer with
	// error 1062; anything else is a real failure and is returned as one.
	$claimed = @mysqli_query(db::$con, "INSERT INTO marketplace_order_map
		(account_id, order_id, remote_order_id, remote_package_id, remote_status, status,
		 lines_json, remote_timestamp, imported_timestamp, updated_timestamp)
		VALUES (
			'" . e($account_id) . "',
			0,
			'" . e(mb_substr((string)$package['remote_order_id'], 0, 64)) . "',
			'" . e($package_id) . "',
			'" . e(mb_substr((string)$package['remote_status'], 0, 40)) . "',
			'importing',
			'" . e(json_encode($package['lines'])) . "',
			'" . e((int)$package['placed_at']) . "',
			UNIX_TIMESTAMP(),
			UNIX_TIMESTAMP())");

	if ($claimed === false) {

		if ((int) mysqli_errno(db::$con) === 1062) {

			// Another pass claimed it between the read and the write. Theirs.
			return array('ok' => true, 'order_id' => 0, 'skipped' => true, 'error' => '');

		}

		return array('ok' => false, 'order_id' => 0, 'skipped' => false, 'error' => (string) mysqli_error(db::$con));

	}

	$map_id = (int) mysqli_insert_id(db::$con);

	$order_id = mp_order_write($account, $package, $map_id);

	if (!$order_id) {

		return array('ok' => false, 'order_id' => 0, 'skipped' => false,
			'error' => (string) db_value("SELECT last_error FROM marketplace_order_map WHERE id = '" . e($map_id) . "'"));

	}

	db("UPDATE marketplace_order_map
		SET order_id = '" . e($order_id) . "', status = 'imported', last_error = '',
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . e($map_id) . "'");

	return array('ok' => true, 'order_id' => $order_id, 'skipped' => false, 'error' => '');

}


/* ------------------------------------------------------- what happened next */

// The marketplace moved the order on. Follow it here.
//
// Three things are worth reacting to and the rest is theirs to manage:
//
//   the tracking number, which the seller did not choose - on this marketplace
//   the carrier is n11's and the number is assigned after collection, so it
//   arrives rather than being sent;
//
//   shipped and delivered, which are the two dates the order timeline reads;
//
//   cancelled, which has to reach the order itself, because a cancelled order
//   that still reads as complete is one the shop will pick, pack and post.
function mp_order_apply_status($account, $package, $map) {

	$order_id = (int) $map['order_id'];

	$stage = mp_can($account['provider'], 'order_stage')
		? mp_call($account['provider'], 'order_stage', array($package['remote_status']))
		: 'open';

	mp_order_apply_tracking($order_id, $package);

	if ($stage === 'shipped' || $stage === 'delivered') {

		mp_order_mark_dates($order_id, $stage);

		return;

	}

	if ($stage === 'cancelled') {

		mp_order_cancel($account, $package, $map);

	}

}

// The number and the carrier, written where the order screen looks for them.
function mp_order_apply_tracking($order_id, $package) {

	$tracking = isset($package['tracking']) ? (array)$package['tracking'] : array();

	$number = isset($tracking['number']) ? trim((string)$tracking['number']) : '';

	if ($number === '') {

		return;

	}

	$ship_to_id = (int) db_value("SELECT id FROM ship_tos WHERE order_id = '" . e((int)$order_id) . "' ORDER BY id ASC LIMIT 1");

	// Written once. The feed hands the same number back on every poll for as
	// long as the package is in the window, and the table has no unique key.
	$exists = db_value("SELECT id FROM shipping_tracking_numbers
		WHERE (order_id = '" . e((int)$order_id) . "') AND (number = '" . e($number) . "')");

	if ($exists) {

		return;

	}

	db("INSERT INTO shipping_tracking_numbers (order_id, ship_to_id, number)
		VALUES ('" . e((int)$order_id) . "', '" . e($ship_to_id) . "', '" . e(mb_substr($number, 0, 100)) . "')");

	$carrier = isset($tracking['carrier']) ? trim((string)$tracking['carrier']) : '';

	if (($carrier !== '') && $ship_to_id) {

		db("UPDATE ship_tos SET shipping_method_code = '" . e(mb_substr($carrier, 0, 50)) . "'
			WHERE (id = '" . e($ship_to_id) . "') AND (shipping_method_code = '')");

	}

}

// The two dates the timeline reads.
//
// Only ever filled in, never moved: the marketplace re-sends a delivered
// package for as long as it is inside the polling window, and re-stamping the
// date each time would make every order look like it shipped this morning.
function mp_order_mark_dates($order_id, $stage) {

	db("UPDATE ship_tos SET ship_date = CURDATE()
		WHERE (order_id = '" . e((int)$order_id) . "') AND (ship_date = '0000-00-00')");

	if ($stage !== 'delivered') {

		return;

	}

	db("UPDATE ship_tos SET delivery_date = CURDATE(), complete = 1
		WHERE (order_id = '" . e((int)$order_id) . "') AND (delivery_date = '0000-00-00')");

}

// The marketplace cancelled it.
//
// process_order_cancellation() is the shop's only sanctioned cancel path and it
// is used, with one argument that matters: attempt_refund false. No gateway
// here ever took this money - the marketplace did - so asking one to give it
// back would at best fail and at worst refund a different order.
//
// It also does not restock, anywhere in this software. For a sale made in the
// shop that is a defensible default, because the goods may have gone out
// already or come back damaged. For a marketplace cancellation it is not: the
// buyer changed their mind, the goods never moved, and every other marketplace
// is still being told there is one fewer than there is. So the stock is put
// back here, and pushed out.
function mp_order_cancel($account, $package, $map) {

	$order_id = (int) $map['order_id'];

	$status = (string) db_value("SELECT status FROM orders WHERE id = '" . e($order_id) . "'");

	if ($status === 'cancelled') {

		return;

	}

	$reason = lang(array(
		'string' => 'Cancelled on {var:1} (order {var:2}).',
		'vars'   => array(mp_provider_name($account['provider']), (string)$package['remote_order_id'])
	));

	$outcome = function_exists('process_order_cancellation')
		? process_order_cancellation($order_id, $reason, true, 0, false)
		: array('status' => 'error', 'message' => '');

	if ($outcome['status'] === 'shipped') {

		// It left before they cancelled it. Nothing here can undo that, and
		// pretending otherwise would take stock back that is on a van.
		db("UPDATE marketplace_order_map
			SET last_error = '" . e(lang('The marketplace cancelled this order after it had shipped.')) . "',
				updated_timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . e((int)$map['id']) . "'");

		return;

	}

	// 'success' is what it answers with, not 'ok'. Worth naming both: the
	// function has four failure words and one success word, and reading it the
	// other way round left the order cancelled and the stock still missing.
	if (($outcome['status'] !== 'success') && ($outcome['status'] !== 'already')) {

		db("UPDATE marketplace_order_map
			SET last_error = '" . e(mb_substr((string)$outcome['message'], 0, 500)) . "',
				updated_timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . e((int)$map['id']) . "'");

		return;

	}

	mp_order_return_stock($order_id);

}

// Put back what the cancelled order took.
function mp_order_return_stock($order_id) {

	$items = (array) db_items("SELECT order_items.product_id, order_items.quantity, products.inventory
		FROM order_items
		INNER JOIN products ON products.id = order_items.product_id
		WHERE order_items.order_id = '" . e((int)$order_id) . "'");

	foreach ($items as $item) {

		if (empty($item['inventory'])) {

			continue;

		}

		$product_id = (int) $item['product_id'];

		db("UPDATE products
			SET inventory_quantity = inventory_quantity + " . max(1, (int)$item['quantity']) . "
			WHERE id = '" . e($product_id) . "'");

		db("UPDATE products SET out_of_stock = '0'
			WHERE (id = '" . e($product_id) . "') AND (inventory_quantity > 0)");

		if (function_exists('pg_marketplace_product_changed')) {

			pg_marketplace_product_changed($product_id, 1);

		}

	}

}

// The three rows, plus the stock. Answers the order id, or 0 with the reason on
// the map row.
function mp_order_write($account, $package, $map_id) {

	$lines = isset($package['lines']) ? (array)$package['lines'] : array();

	if (!$lines) {

		mp_order_failed($map_id, lang('The marketplace sent an order with no lines.'));

		return 0;

	}

	// Every line is matched to a product before anything is written. A line
	// nobody can identify is the one thing worth refusing the whole order over:
	// an order with a missing line understates what has to be shipped, and it
	// takes the stock off the wrong things.
	$matched = array();

	foreach ($lines as $line) {

		$product = mp_order_match_product($account, $line);

		if (!$product) {

			mp_order_failed($map_id, lang(array(
				'string' => 'No product here matches the code {var:1} ({var:2}). Match it on the Marketplaces screen, then try again.',
				'vars'   => array((string)$line['code'], (string)$line['name'])
			)));

			return 0;

		}

		$matched[] = array('line' => $line, 'product' => $product);

	}

	$customer = isset($package['customer']) ? (array)$package['customer'] : array();

	$billing = isset($package['billing']) ? (array)$package['billing'] : array();

	$contact_id = mp_order_contact($customer, $billing);

	$subtotal = 0;

	$tax = 0;

	$discount = 0;

	foreach ($matched as $entry) {

		$quantity = max(1, (int)$entry['line']['quantity']);

		$subtotal += ((int)$entry['line']['price']) * $quantity;

		$tax      += ((int)$entry['line']['tax']) * $quantity;

		$discount += ((int)$entry['line']['discount']) * $quantity;

	}

	$shipping = isset($package['shipping_cost']) ? (int)$package['shipping_cost'] : 0;

	$total = $subtotal - $discount + $tax + $shipping;

	$placed_at = isset($package['placed_at']) ? (int)$package['placed_at'] : time();

	if ($placed_at <= 0) {

		$placed_at = time();

	}

	// A marketplace order is a sale to a customer, so it is typed as one and
	// shows up in the order list beside the web orders rather than behind a
	// filter nobody changed.
	//
	// payment_method is left alone on purpose. It is an ENUM of the gateways
	// this software can charge through, and none of them took this money - the
	// marketplace did, and settles it later. Writing the marketplace's name
	// into it would be stored as the empty string anyway, because sql_mode is
	// blank here and an out-of-range ENUM is silently blanked rather than
	// refused. Their order number goes in transaction_id, which is where the
	// order screen already looks for the reference a payment carries.
	db("INSERT INTO orders SET
		type            = 'marketplace',
		status          = 'complete',
		order_date      = '" . e($placed_at) . "',
		paid_at         = '" . e($placed_at) . "',
		reference_code  = '" . e(generate_order_reference_code()) . "',
		contact_id      = '" . e((int)$contact_id) . "',
		user_id         = 0,
		subtotal        = '" . e((int)$subtotal) . "',
		discount        = '" . e((int)$discount) . "',
		tax             = '" . e((int)$tax) . "',
		shipping        = '" . e((int)$shipping) . "',
		surcharge       = 0,
		total           = '" . e((int)$total) . "',
		currency_code   = '" . e(mb_substr((string)$package['currency'], 0, 3)) . "',
		transaction_id  = '" . e(mb_substr((string)$package['remote_order_id'], 0, 50)) . "',
		billing_first_name   = '" . e(mb_substr((string)$customer['first_name'], 0, 50)) . "',
		billing_last_name    = '" . e(mb_substr((string)$customer['last_name'], 0, 50)) . "',
		billing_email_address = '" . e(mb_substr((string)$customer['email'], 0, 100)) . "',
		billing_company      = '" . e(mb_substr((string)$customer['company'], 0, 50)) . "',
		billing_phone_number = '" . e(mb_substr((string)$customer['phone'], 0, 50)) . "',
		billing_address_1    = '" . e(mb_substr((string)$billing['address_1'], 0, 50)) . "',
		billing_address_2    = '" . e(mb_substr((string)$billing['address_2'], 0, 50)) . "',
		billing_city         = '" . e(mb_substr((string)$billing['city'], 0, 50)) . "',
		billing_state        = '" . e(mb_substr((string)$billing['state'], 0, 50)) . "',
		billing_zip_code     = '" . e(mb_substr((string)$billing['zip'], 0, 50)) . "',
		billing_country      = '" . e(mb_substr((string)$billing['country'], 0, 50)) . "',
		opt_in          = 0,
		last_modified_timestamp = UNIX_TIMESTAMP()");

	$order_id = (int) mysqli_insert_id(db::$con);

	if (!$order_id) {

		mp_order_failed($map_id, lang('The order could not be written.'));

		return 0;

	}

	// The recipient, when the marketplace told us where it goes and this shop
	// ships at all. order_items.ship_to_id may be 0 and often is; what a
	// ship_to buys here is the address on the packing slip and the two dates
	// the order timeline reads.
	$ship_to_id = mp_order_ship_to($order_id, $package);

	foreach ($matched as $entry) {

		$line = $entry['line'];

		$quantity = max(1, (int)$line['quantity']);

		db("INSERT INTO order_items SET
			order_id     = '" . e($order_id) . "',
			ship_to_id   = '" . e((int)$ship_to_id) . "',
			product_id   = '" . e((int)$entry['product']['id']) . "',
			product_name = '" . e(mb_substr((string)$entry['product']['name'], 0, 100)) . "',
			quantity     = '" . e($quantity) . "',
			price        = '" . e((int)$line['price']) . "',
			tax          = '" . e((int)$line['tax']) . "',
			shipping     = 0");

	}

	// The order number last, and on its own, because allocating it takes a
	// table lock that no other query may run inside.
	$order_number = mp_order_number();

	if ($order_number) {

		db("UPDATE orders SET order_number = '" . e($order_number) . "' WHERE id = '" . e($order_id) . "'");

	}

	mp_order_take_stock($matched);

	log_activity(lang(array(
		'string' => 'Order {var:1} was imported from {var:2}.',
		'vars'   => array((string)$package['remote_order_id'], mp_provider_name($account['provider']))
	)), 'system');

	// Announced the way a web order is announced. An order that arrives without
	// a sound is an order that ships late, and this one has a marketplace's
	// dispatch clock running on it.
	if (function_exists('create_notification')) {

		create_notification(array(
			'action'      => 'new_order',
			'type'        => 'success',
			'title'       => mp_provider_name($account['provider']) . ' - ' . (string)$package['remote_order_id'],
			'order_id'    => $order_id,
			'order_total' => $total,
			'user'        => 'system'
		));

	}

	return $order_id;

}

// Which product here is the marketplace talking about.
//
// The mapping table first, because that is the answer the operator gave and it
// is allowed to disagree with the catalogue. Only then the product's own code,
// which is what a shop that lists under the same codes on both sides relies on
// and saves it from having to map every product by hand.
function mp_order_match_product($account, $line) {

	$code = isset($line['code']) ? trim((string)$line['code']) : '';

	if ($code === '') {

		return null;

	}

	$product = db_item("SELECT products.id, products.name, products.inventory, products.inventory_quantity
		FROM marketplace_product_map
		INNER JOIN products ON products.id = marketplace_product_map.product_id
		WHERE (marketplace_product_map.account_id = '" . e((int)$account['id']) . "')
		  AND (marketplace_product_map.remote_code = '" . e($code) . "')
		LIMIT 1");

	if ($product) {

		return $product;

	}

	$product = db_item("SELECT id, name, inventory, inventory_quantity
		FROM products WHERE name = '" . e($code) . "' LIMIT 1");

	if (!$product) {

		return null;

	}

	// Matched on the code alone, so the mapping is written now.
	//
	// The marketplace has just said this product is listed over there under
	// this code, which is better evidence than anything the operator could have
	// typed. Without the row nothing else would know: stock changes here are
	// queued per mapping, so a product that sells on the marketplace but was
	// never mapped would drift out of step from its very first sale - which is
	// exactly the product this shop can least afford to oversell.
	db("INSERT INTO marketplace_product_map
		(account_id, product_id, remote_code, status, created_timestamp)
		VALUES (
			'" . e((int)$account['id']) . "',
			'" . e((int)$product['id']) . "',
			'" . e($code) . "',
			'active',
			UNIX_TIMESTAMP())");

	return $product;

}

// The customer, found or made.
//
// Found by e-mail, which is the only thing a marketplace reliably gives that
// this shop also holds. contacts.email_address is indexed but not unique, so
// the oldest match wins - if the same person has two contact rows, the older is
// the one their history hangs off.
//
// A marketplace that hides the address behind a relay - most do - means every
// order from it lands on one contact. That is wrong, so an address that does
// not look like a person's own is not matched on at all and the order gets its
// own contact row.
function mp_order_contact($customer, $billing) {

	$email = isset($customer['email']) ? trim((string)$customer['email']) : '';

	$first = isset($customer['first_name']) ? trim((string)$customer['first_name']) : '';

	$last = isset($customer['last_name']) ? trim((string)$customer['last_name']) : '';

	if (($email !== '') && mp_order_email_is_personal($email)) {

		$found = db_value("SELECT id FROM contacts
			WHERE email_address = '" . e($email) . "' ORDER BY id ASC LIMIT 1");

		if ($found) {

			return (int) $found;

		}

	}

	db("INSERT INTO contacts SET
		first_name       = '" . e(mb_substr($first, 0, 50)) . "',
		last_name        = '" . e(mb_substr($last, 0, 50)) . "',
		email_address    = '" . e(mb_substr($email, 0, 100)) . "',
		company          = '" . e(mb_substr(isset($customer['company']) ? (string)$customer['company'] : '', 0, 50)) . "',
		business_address_1 = '" . e(mb_substr(isset($billing['address_1']) ? (string)$billing['address_1'] : '', 0, 50)) . "',
		business_city    = '" . e(mb_substr(isset($billing['city']) ? (string)$billing['city'] : '', 0, 50)) . "',
		business_state   = '" . e(mb_substr(isset($billing['state']) ? (string)$billing['state'] : '', 0, 50)) . "',
		business_zip_code = '" . e(mb_substr(isset($billing['zip']) ? (string)$billing['zip'] : '', 0, 10)) . "',
		business_country = '" . e(mb_substr(isset($billing['country']) ? (string)$billing['country'] : '', 0, 50)) . "',
		business_phone   = '" . e(mb_substr(isset($customer['phone']) ? (string)$customer['phone'] : '', 0, 50)) . "',
		opt_in           = 0,
		timestamp        = UNIX_TIMESTAMP()");

	return (int) mysqli_insert_id(db::$con);

}

// Is this a person's address, or a marketplace's forwarder?
//
// The large marketplaces hand out a per-order relay address, and matching on
// one of those would file every order they ever send under a single customer.
// The test is the domain: a relay belongs to the marketplace, and a marketplace
// domain is not where a buyer keeps their mail.
function mp_order_email_is_personal($email) {

	$at = strrpos($email, '@');

	if ($at === false) {

		return false;

	}

	$domain = strtolower(substr($email, $at + 1));

	$relays = array(
		'n11.com', 'mail.n11.com', 'pazaryeri.n11.com',
		'trendyol.com', 'mail.trendyol.com',
		'hepsiburada.com', 'mail.hepsiburada.com',
		'marketplace.amazon.com', 'marketplace.amazon.com.tr'
	);

	foreach ($relays as $relay) {

		if ($domain === $relay || substr($domain, -strlen('.' . $relay)) === '.' . $relay) {

			return false;

		}

	}

	return true;

}

// The recipient row, when there is an address worth keeping.
function mp_order_ship_to($order_id, $package) {

	$address = isset($package['shipping_address']) ? (array)$package['shipping_address'] : array();

	$name = isset($address['name']) ? trim((string)$address['name']) : '';

	if ($name === '' && empty($address['address_1'])) {

		return 0;

	}

	if ($name === '') {

		$name = lang('myself');

	}

	db("INSERT INTO ship_tos SET
		order_id        = '" . e((int)$order_id) . "',
		ship_to_name    = '" . e(mb_substr($name, 0, 50)) . "',
		address_1       = '" . e(mb_substr(isset($address['address_1']) ? (string)$address['address_1'] : '', 0, 50)) . "',
		address_2       = '" . e(mb_substr(isset($address['address_2']) ? (string)$address['address_2'] : '', 0, 50)) . "',
		city            = '" . e(mb_substr(isset($address['city']) ? (string)$address['city'] : '', 0, 50)) . "',
		state           = '" . e(mb_substr(isset($address['state']) ? (string)$address['state'] : '', 0, 50)) . "',
		zip_code        = '" . e(mb_substr(isset($address['zip']) ? (string)$address['zip'] : '', 0, 50)) . "',
		country         = '" . e(mb_substr(isset($address['country']) ? (string)$address['country'] : '', 0, 50)) . "',
		phone_number    = '" . e(mb_substr(isset($address['phone']) ? (string)$address['phone'] : '', 0, 50)) . "'");

	return (int) mysqli_insert_id(db::$con);

}

// The next order number.
//
// Same four statements the checkout uses (submit_order.php), and the reason
// they are alone in this function rather than inline above: LOCK TABLES applies
// to the whole connection, so between the lock and the unlock every query
// against any other table fails with "was not locked with LOCK TABLES". Nothing
// else may happen in here.
function mp_order_number() {

	$number = 0;

	db("LOCK TABLES next_order_number WRITE");

	$row = db_item("SELECT next_order_number FROM next_order_number LIMIT 1");

	if ($row) {

		$number = (int) $row['next_order_number'];

		db("UPDATE next_order_number SET next_order_number = next_order_number + 1");

	} else {

		$number = 1;

		db("INSERT INTO next_order_number (next_order_number) VALUES (2)");

	}

	db("UNLOCK TABLES");

	return $number;

}

// The stock comes off, which is the reason for importing the order at all.
//
// Same rule the checkout follows: only a product that tracks stock, and only
// down to zero rather than through it - a marketplace can sell what this shop
// thinks it does not have, and a negative quantity would then be pushed back
// out to every other marketplace as if it meant something.
function mp_order_take_stock($matched) {

	foreach ($matched as $entry) {

		$product = $entry['product'];

		if (empty($product['inventory'])) {

			continue;

		}

		$quantity = max(1, (int)$entry['line']['quantity']);

		$product_id = (int) $product['id'];

		db("UPDATE products
			SET inventory_quantity = GREATEST(0, CAST(inventory_quantity AS SIGNED) - " . (int)$quantity . ")
			WHERE id = '" . e($product_id) . "'");

		$left = (int) db_value("SELECT inventory_quantity FROM products WHERE id = '" . e($product_id) . "'");

		if ($left <= 0) {

			db("UPDATE products SET out_of_stock = '1', out_of_stock_timestamp = UNIX_TIMESTAMP()
				WHERE (id = '" . e($product_id) . "') AND (out_of_stock != '1')");

			if (function_exists('create_notification')) {

				create_notification(array(
					'action'     => 'out_stock',
					'type'       => 'warning',
					'title'      => $product['name'],
					'product_id' => $product_id,
					'user'       => 'system'
				));

			}

		}

		// And back out to wherever else this product is listed. This is the
		// half that makes importing worth doing: a sale on one marketplace
		// takes the item off the shelf on all of them.
		if (function_exists('pg_marketplace_product_changed')) {

			pg_marketplace_product_changed($product_id, 1);

		}

	}

}

function mp_order_failed($map_id, $message) {

	db("UPDATE marketplace_order_map
		SET status = 'failed',
			last_error = '" . e(mb_substr((string)$message, 0, 500)) . "',
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . e((int)$map_id) . "'");

}
