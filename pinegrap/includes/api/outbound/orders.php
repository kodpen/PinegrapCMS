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
require_once(dirname(__FILE__) . '/webhooks.php');

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
//                      price, tax, discount, and optionally discount_total
//                      and sku)
//
// price, tax and discount are minor units PER UNIT, because that is what
// order_items stores and converting once here is better than three connectors
// each getting it wrong differently. discount_total, when a connector gives it,
// is the whole line's discount and is used instead of discount x quantity: a
// marketplace that discounts the line rather than the unit has an amount that
// does not always divide by the quantity.
//
// code is the provider's key for the product (the seller's code on n11, the
// barcode on Trendyol); sku, when a connector gives it, is the seller's own
// code beside a barcode, which is the product's name here.
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

		// A package refused before any order was written - most often a line
		// no product here matched - is taken again, so matching the product
		// and letting the feed hand the package back is enough to bring it in.
		// The UPDATE is the claim: only a row still 'failed' with no order, and
		// a pass that loses the race to it changes nothing. An 'importing' row
		// with no order is left alone; it may be a write in progress, or one
		// that stopped half-way and left an order behind.
		if (($existing['status'] === 'failed') && !(int)$existing['order_id']) {

			db("UPDATE marketplace_order_map
				SET status = 'importing',
					remote_order_id = '" . e(mb_substr((string)$package['remote_order_id'], 0, 64)) . "',
					remote_status = '" . e(mb_substr((string)$package['remote_status'], 0, 40)) . "',
					lines_json = '" . e(json_encode($package['lines'])) . "',
					remote_timestamp = '" . e((int)$package['placed_at']) . "',
					last_error = '',
					updated_timestamp = UNIX_TIMESTAMP()
				WHERE (id = '" . e((int)$existing['id']) . "') AND (status = 'failed') AND (order_id = 0)");

			if (mysqli_affected_rows(db::$con) === 1) {

				return mp_import_claimed($account, $package, (int)$existing['id']);

			}

		}

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

	return mp_import_claimed($account, $package, (int) mysqli_insert_id(db::$con));

}

// Write the order of a package whose map row this pass holds ('importing').
// Answers the same shape as mp_import_order().
function mp_import_claimed($account, $package, $map_id) {

	// Cancelled before an order was written for it - between two pulls, before
	// the first one, or while a refused package waited for its product to be
	// matched. Nothing is written: there is nothing to pick, and writing the
	// order only to cancel it would take the stock and give it back, which is
	// not a round trip when the stock was short (the take stops at zero, the
	// return adds the whole quantity). The map row stays, so the package is
	// never imported later, and says why there is no order.
	if (mp_order_stage($account, $package) === 'cancelled') {

		db("UPDATE marketplace_order_map
			SET status = 'imported',
				last_error = '" . e(lang('Cancelled on the marketplace before it was imported. No order was written.')) . "',
				updated_timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . e($map_id) . "'");

		return array('ok' => true, 'order_id' => 0, 'skipped' => true, 'error' => '');

	}

	$order_id = mp_order_write($account, $package, $map_id);

	if (!$order_id) {

		return array('ok' => false, 'order_id' => 0, 'skipped' => false,
			'error' => (string) db_value("SELECT last_error FROM marketplace_order_map WHERE id = '" . e($map_id) . "'"));

	}

	db("UPDATE marketplace_order_map
		SET order_id = '" . e($order_id) . "', status = 'imported', last_error = '',
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . e($map_id) . "'");

	// Before the status below, so a receiver hears of the order before an
	// order.shipped or order.delivered of the same pass.
	mp_order_announce('marketplace.order.imported', $account, $map_id, (string)$package['remote_status']);

	// A package can also arrive already shipped or delivered, with its tracking
	// number. The feed is not guaranteed to hand it back, so what a known
	// package would get on its next poll it gets now.
	mp_order_apply_status($account, $package, array('id' => $map_id, 'order_id' => $order_id));

	return array('ok' => true, 'order_id' => $order_id, 'skipped' => false, 'error' => '');

}


/* ------------------------------------------------------- what happened next */

// The marketplace moved the order on. Follow it here.
//
// Three things are worth reacting to and the rest is theirs to manage:
//
//   the tracking number, which the seller did not choose - on n11 and on
//   Trendyol the carrier is the marketplace's and so is the number, so it
//   arrives rather than being sent;
//
//   shipped and delivered, which are the two dates the order timeline reads;
//
//   cancelled, which has to reach the order itself, because a cancelled order
//   that still reads as complete is one the shop will pick, pack and post.
function mp_order_apply_status($account, $package, $map) {

	$order_id = (int) $map['order_id'];

	$stage = mp_order_stage($account, $package);

	mp_order_apply_tracking($order_id, $package, $account);

	if ($stage === 'shipped' || $stage === 'delivered') {

		mp_order_mark_dates($order_id, $stage);

		return;

	}

	if ($stage === 'cancelled') {

		mp_order_cancel($account, $package, $map);

	}

}

// Where the package is, in the shop's four words: open, shipped, delivered or
// cancelled. A provider that cannot say is taken as open.
function mp_order_stage($account, $package) {

	if (!mp_can($account['provider'], 'order_stage')) {

		return 'open';

	}

	return (string) mp_call($account['provider'], 'order_stage',
		array(isset($package['remote_status']) ? (string)$package['remote_status'] : ''));

}

// One batch's packages (a pull's pass or a webhook delivery) with the cancelled
// ones first, the rest in the order they were read, each package once.
//
// A split or partly cancelled package comes back as the old package cancelled
// and the goods under a new package id. The cancellation has to give the old
// order's stock back before the new order takes it: the take stops at zero and
// the return adds the whole quantity, so the other way round leaves the stock
// higher than the shelf, and that number is pushed to every marketplace.
//
// A package that changed while the pages were walked is in the batch twice
// (mp_feed_first_time()). Only its newest copy is kept, where it was read last:
// moved ahead of the older one, the older copy would write its status back.
function mp_order_cancellations_first($account, $packages) {

	$latest = array();

	foreach (array_values($packages) as $index => $package) {

		$id = isset($package['remote_package_id']) ? (string) $package['remote_package_id'] : '';

		$key = ($id === '') ? '#' . $index : 'p' . $id;

		unset($latest[$key]);

		$latest[$key] = $package;

	}

	$cancelled = array();

	$others = array();

	foreach ($latest as $package) {

		if (mp_order_stage($account, $package) === 'cancelled') {

			$cancelled[] = $package;

		} else {

			$others[] = $package;

		}

	}

	return array_merge($cancelled, $others);

}

// The number and the carrier, written where the order screen looks for them.
//
// Through the shipment write path (pg_shipment_record()), with the carrier's
// name and its own tracking page as the marketplace gives them, so the row
// says which courier took the parcel and links where the marketplace does.
//
// The carrier's name also becomes the address's shipping method code, unless
// the account ships with a carrier of its own: there the marketplace's carrier
// is not the one that takes the parcel, and a parcel the shop records would
// otherwise take that carrier and its tracking page from the method code. The
// account is read again for that, not taken as the pass loaded it: an operator
// who switches to the shop's own carrier during a long pull would otherwise
// get the marketplace's carrier on packages imported after the switch.
function mp_order_apply_tracking($order_id, $package, $account = null) {

	$tracking = isset($package['tracking']) ? (array)$package['tracking'] : array();

	$number = isset($tracking['number']) ? pg_shipment_number_clean($tracking['number']) : '';

	if ($number === '') {

		return;

	}

	$ship_to_id = (int) db_value("SELECT id FROM ship_tos WHERE order_id = '" . e((int)$order_id) . "' ORDER BY id ASC LIMIT 1");

	$carrier = isset($tracking['carrier']) ? trim((string)$tracking['carrier']) : '';

	// Written once. The feed hands the same number back on every poll for as
	// long as the package is in the window; the write path updates the row it
	// already has and says whether the number is new.
	$recorded = pg_shipment_record((int)$order_id, $ship_to_id, $number, array(
		'carrier'      => $carrier,
		'tracking_url' => isset($tracking['link']) ? (string)$tracking['link'] : '',
		'source'       => 'marketplace'
	));

	if (!$recorded['created']) {

		return;

	}

	// The same event the order screen and the API produce. Nothing is mailed
	// from here - the marketplace tells its own buyer - but an integration
	// listening for shipments has to hear about a parcel whichever side
	// recorded it. The guard above means this runs once per number, not once
	// per poll.
	api_webhook_enqueue('order.shipped', array(
		'id'               => (int)$order_id,
		'order_number'     => db_value("SELECT order_number FROM orders WHERE id = '" . e((int)$order_id) . "' LIMIT 1"),
		'ship_to_id'       => (int)$ship_to_id,
		'tracking_numbers' => array($recorded['number']),
		'ship_date'        => null,
		'parcels'          => pg_shipment_api_parcels((int)$ship_to_id, null, $recorded['number'])
	));

	$ships_itself = false;

	if (($carrier !== '') && $ship_to_id && is_array($account)) {

		$current = isset($account['id']) ? mp_account((int)$account['id']) : null;

		$ships_itself = mp_account_ships_itself(is_array($current) ? $current : $account);

	}

	if (($carrier !== '') && $ship_to_id && !$ships_itself) {

		db("UPDATE ship_tos SET shipping_method_code = '" . e(mb_substr($carrier, 0, 50)) . "'
			WHERE (id = '" . e($ship_to_id) . "') AND (shipping_method_code = '')");

	}

}

// The two dates the timeline reads, and the delivery itself.
//
// Only ever filled in, never moved: the marketplace re-sends a delivered
// package for as long as it is inside the polling window, and re-stamping the
// date each time would make every order look like it shipped this morning.
//
// A delivered package is recorded through pg_shipment_delivered(), address by
// address, on every poll that reports it: the parcels carry the record, so
// order.delivered is announced the first time and never again. No e-mail is
// started (the marketplace tells its own buyer). An address without a parcel
// number has no row to keep that record on, so it is announced only by the
// poll whose update filled in its empty delivery date.
function mp_order_mark_dates($order_id, $stage) {

	db("UPDATE ship_tos SET ship_date = CURDATE()
		WHERE (order_id = '" . e((int)$order_id) . "') AND (ship_date = '0000-00-00')");

	if ($stage !== 'delivered') {

		return;

	}

	$now = time();

	foreach (db_values("SELECT id FROM ship_tos WHERE order_id = '" . e((int)$order_id) . "' ORDER BY id ASC") as $ship_to_id) {

		db("UPDATE ship_tos SET delivery_date = CURDATE(), complete = 1
			WHERE (id = '" . e((int)$ship_to_id) . "') AND (delivery_date = '0000-00-00')");

		$first = (mysqli_affected_rows(db::$con) > 0);

		pg_shipment_delivered((int)$ship_to_id, $now, array('notify' => false, 'first' => $first));

	}

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

	// Only a cancellation this call made: 'already' means somebody else
	// cancelled the order first, and a shipped one returned above.
	if ($outcome['status'] === 'success') {

		mp_order_announce('marketplace.order.cancelled', $account, (int)$map['id'], (string)$package['remote_status']);

	}

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

		// The stamp lets a system syncing with GET /products?updated_since=
		// list the product; this path announces no webhook event.
		db("UPDATE products
			SET inventory_quantity = inventory_quantity + " . max(1, (int)$item['quantity']) . ",
				timestamp = UNIX_TIMESTAMP()
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

	$totals = mp_order_totals(array_column($matched, 'line'),
		isset($package['shipping_cost']) ? $package['shipping_cost'] : 0);

	$subtotal = $totals['subtotal'];

	$tax = $totals['tax'];

	$discount = $totals['discount'];

	$shipping = $totals['shipping'];

	$total = $totals['total'];

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

// What the lines add up to, in minor units: array(subtotal, discount, tax,
// shipping, total). The discount is each line's discount_total where the
// connector gave one, and its unit discount times the quantity otherwise.
function mp_order_totals($lines, $shipping = 0) {

	$subtotal = 0;

	$tax = 0;

	$discount = 0;

	foreach ((array)$lines as $line) {

		$quantity = max(1, (int)$line['quantity']);

		$subtotal += ((int)$line['price']) * $quantity;

		$tax      += ((int)$line['tax']) * $quantity;

		$discount += isset($line['discount_total'])
			? (int)$line['discount_total']
			: ((int)$line['discount']) * $quantity;

	}

	$shipping = (int) $shipping;

	return array(
		'subtotal' => $subtotal,
		'discount' => $discount,
		'tax'      => $tax,
		'shipping' => $shipping,
		'total'    => $subtotal - $discount + $tax + $shipping
	);

}

// The one row a lookup found, or null when it found none or more than one.
function mp_order_single_match($rows) {

	return (is_array($rows) && (count($rows) === 1)) ? reset($rows) : null;

}

// Which product here is the marketplace talking about.
//
// The mapping table first, because that is the answer the operator gave and it
// is allowed to disagree with the catalogue. Then the catalogue, which is what a
// shop that lists under the same codes on both sides relies on and saves it
// from having to map every product by hand:
//
//   the product's barcode, for a marketplace that knows products by barcode
//   (Trendyol), where the line's code is a barcode;
//
//   the product's name against the line's sku, the seller's own code that
//   such a marketplace carries beside the barcode (a Trendyol listing is sent
//   with the name as its stock code);
//
//   the product's name against the code itself, which is the seller's code on
//   a marketplace that has no barcode key (n11).
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

	// remote_code compares byte for byte (utf8mb4_bin), so "abc-1" on the
	// order does not find the product mapped as "ABC-1". A marketplace that
	// sends the code in another case still finds it, but only when one
	// mapping of the account answers: two codes that differ only in case are
	// two products, and picking either would sell the other's stock.
	$product = mp_order_single_match((array) db_items("SELECT products.id, products.name, products.inventory, products.inventory_quantity
		FROM marketplace_product_map
		INNER JOIN products ON products.id = marketplace_product_map.product_id
		WHERE (marketplace_product_map.account_id = '" . e((int)$account['id']) . "')
		  AND (marketplace_product_map.remote_code COLLATE utf8mb4_unicode_ci = '" . e($code) . "')
		LIMIT 2"));

	if ($product) {

		return $product;

	}

	if (mp_provider_keys_by_barcode($account['provider'])) {

		$product = db_item("SELECT products.id, products.name, products.inventory, products.inventory_quantity
			FROM product_barcodes
			INNER JOIN products ON products.id = product_barcodes.product_id
			WHERE product_barcodes.barcode = '" . e($code) . "'
			LIMIT 1");

	}

	$sku = isset($line['sku']) ? trim((string)$line['sku']) : '';

	if (!$product && $sku !== '') {

		$product = db_item("SELECT id, name, inventory, inventory_quantity
			FROM products WHERE name = '" . e($sku) . "' LIMIT 1");

	}

	if (!$product) {

		$product = db_item("SELECT id, name, inventory, inventory_quantity
			FROM products WHERE name = '" . e($code) . "' LIMIT 1");

	}

	if (!$product) {

		return null;

	}

	// Matched on the catalogue alone, so the mapping is written now, under the
	// line's code - the marketplace's key, whichever way the product was found.
	//
	// The marketplace has just said this product is listed over there under
	// this code, which is better evidence than anything the operator could have
	// typed. Without the row nothing else would know: stock changes here are
	// queued per mapping, so a product that sells on the marketplace but was
	// never mapped would drift out of step from its very first sale - which is
	// exactly the product this shop can least afford to oversell.
	//
	// IGNORE because a product is mapped once per account: one already mapped
	// under another code (a second barcode of the same product) keeps the
	// mapping the operator gave it, where a plain INSERT would stop the job on
	// the duplicate key.
	db("INSERT IGNORE INTO marketplace_product_map
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
		'trendyol.com', 'mail.trendyol.com', 'trendyolmail.com',
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

		// The stamp lets a system syncing with GET /products?updated_since=
		// list the product; this path announces no webhook event.
		db("UPDATE products
			SET inventory_quantity = GREATEST(0, CAST(inventory_quantity AS SIGNED) - " . (int)$quantity . "),
				timestamp = UNIX_TIMESTAMP()
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


/* ------------------------------------------------------------------- events */

// What a marketplace.order.* event carries. id is the order's, as in every
// order.* event; then the account, its provider and the marketplace's own
// order and package ids, and the package's status as the event saw it (the map
// row may not have caught up yet, so the caller can name it). Nothing else of
// the account goes out: its credentials never leave this file. An extra field
// (the tracking link of a shipment) is added after the base fields and never
// replaces one.
function mp_order_event_payload($account, $map, $order_number, $remote_status = '', $extra = array()) {

	$payload = array(
		'id'                => (int)$map['order_id'],
		'order_number'      => (string)$order_number,
		'account_id'        => (int)$account['id'],
		'provider'          => (string)$account['provider'],
		'remote_order_id'   => (string)$map['remote_order_id'],
		'remote_package_id' => (string)$map['remote_package_id'],
		'remote_status'     => ((string)$remote_status !== '') ? (string)$remote_status : (string)$map['remote_status']
	);

	return $payload + (array)$extra;

}

// Announce a marketplace.order.* event for one map row. Queued, never sent
// here, and silent without the API (pg_announce()), so an event is never the
// reason an import, a cancellation or a shipment report fails. A row without
// an order (a package cancelled before its import) has nothing to announce.
function mp_order_announce($event, $account, $map_id, $remote_status = '', $extra = array()) {

	if (!function_exists('pg_announce')) {

		return;

	}

	$map = db_item("SELECT order_id, remote_order_id, remote_package_id, remote_status
		FROM marketplace_order_map
		WHERE id = '" . e((int)$map_id) . "'");

	if (!$map || ((int)$map['order_id'] <= 0)) {

		return;

	}

	$order_number = (string)db_value("SELECT order_number FROM orders WHERE id = '" . e((int)$map['order_id']) . "'");

	pg_announce($event, mp_order_event_payload($account, $map, $order_number, $remote_status, $extra));

}
