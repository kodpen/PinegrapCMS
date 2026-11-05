<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Stock, written the only way it is safe to write it.
//
// The endpoint this replaces read the current quantity into PHP, added or
// subtracted there, and wrote the result back. Two channels selling the last
// item at the same moment both read the same number and both wrote the same
// answer, so one sale disappeared and the shop oversold. That is not a rare
// race - it is precisely the moment stock matters.
//
// Here the arithmetic is in the statement, so the database applies both changes
// in turn and neither can be lost. The cast to SIGNED is not decoration:
// inventory_quantity is an unsigned column, and subtracting past zero from an
// unsigned value is an out-of-range error rather than a negative number, which
// is why the floor is applied with GREATEST inside the same expression.
//
// Stock is also where a retried call does real harm, so this endpoint is the
// main reason the Idempotency-Key header exists.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(dirname(dirname(__FILE__)) . '/outbound/webhooks.php');

// The largest stock level or change one call may name, either way. The route
// table declares the same bound for the single-product endpoint
// (inventory.write in includes/api/schema.php), where the router enforces it;
// a batch item is not a route parameter and is read by
// api_inventory_quantity() instead. Well inside the unsigned INT column, which
// the session's non-strict mode would otherwise clamp in silence.
if (!defined('API_INVENTORY_QUANTITY_LIMIT')) {
	define('API_INVENTORY_QUANTITY_LIMIT', 1000000);
}

// A batch item's quantity read the way the router reads the single endpoint's
// int parameter (api_validate_value()): a whole number, from JSON or from a
// numeric string, nothing after a decimal point. Returns the int, null when it
// is not a whole number, false when it is one outside the bound above.
function api_inventory_quantity($raw) {

	if (is_bool($raw) || !is_scalar($raw) || !is_numeric($raw) || ((string)(int)$raw !== trim((string)$raw))) {

		return null;

	}

	$quantity = (int)$raw;

	if (($quantity < -API_INVENTORY_QUANTITY_LIMIT) || ($quantity > API_INVENTORY_QUANTITY_LIMIT)) {

		return false;

	}

	return $quantity;

}

// The location a stock call names: 0 for none (the default location, or the
// total on a site without locations), the id when it is one of the site's
// locations, null when it is not.
function api_inventory_location($raw) {

	return pg_stock_requested_location($raw);

}

function api_inventory_write($params) {

	$location = api_inventory_location(isset($params['location']) ? $params['location'] : null);

	if ($location === null) {

		api_fail(422, 'location', lang('location is not a stock location of this site.'));

	}

	$result = api_inventory_apply((int)$params['id'], '', $params['op'], (int)$params['quantity'], true, api_dry_run_requested(), $location);

	if ($result['status'] === 'not_tracked') {

		api_fail(409, 'not_tracked', lang('This product does not track stock. Send op=set with a quantity to start tracking it.'));

	}

	if ($result['status'] === 'bundle') {

		api_fail(409, 'bundle', lang('This product is a bundle: its stock follows the products in it. Change their stock instead.'));

	}

	if ($result['status'] === 'refused') {

		api_fail(409, 'refused', lang('The stock could not be changed at this location: the change would pass the largest quantity it can hold.'));

	}

	if ($result['status'] !== 'ok') {

		api_fail_not_found(lang('Product'));

	}

	// The product was found and it tracks stock, which is everything this
	// endpoint can check without writing. The levels are not quoted back: they
	// would be the levels before the change, and a caller reading them as the
	// result is exactly the mistake worth not inviting.
	api_dry_run_stop('updated', 'inventory', array(
		'id'       => (int)$params['id'],
		'op'       => $params['op'],
		'quantity' => (int)$params['quantity'],
		'location' => $location
	));

	api_ok($result['inventory']);

}

// Marketplaces push stock in batches rather than one product at a time, so the
// batch is a first-class endpoint rather than something the caller loops over -
// two hundred separate requests would spend the rate ceiling on plumbing.
//
// Each item is reported on individually and a bad row does not throw away the
// good ones: a sync that fails wholesale because one product was deleted last
// week is a sync that stops running.
function api_inventory_bulk($params) {

	$items = $params['items'];

	// Checked once for the whole batch: every item below runs its own checks and
	// stops short of the write.
	$dry = api_dry_run_requested();

	$results = array();

	$applied = 0;

	foreach ($items as $index => $item) {

		if (!is_array($item)) {

			$results[] = array('index' => $index, 'status' => 'invalid', 'message' => lang('Each item must be an object.'));

			continue;

		}

		// sku is the product id under another name, so it feeds the same field.
		// barcode is the EAN/UPC and needs a lookup.
		$id = isset($item['id']) ? (int)$item['id'] : 0;

		if ($id <= 0 && isset($item['sku']) && ctype_digit(trim((string)$item['sku']))) {

			$id = (int)trim($item['sku']);

		}

		$sku = isset($item['barcode']) ? trim((string)$item['barcode']) : '';

		if ($id <= 0 && $sku === '') {

			$results[] = array('index' => $index, 'status' => 'invalid', 'message' => lang('Each item needs an id, an sku or a barcode.'));

			continue;

		}

		$op = isset($item['op']) ? strtolower(trim((string)$item['op'])) : '';

		if ($op !== 'set' && $op !== 'adjust') {

			$results[] = array('index' => $index, 'id' => $id, 'sku' => $sku, 'status' => 'invalid', 'message' => lang('op must be set or adjust.'));

			continue;

		}

		$quantity = api_inventory_quantity(isset($item['quantity']) ? $item['quantity'] : null);

		if ($quantity === null) {

			$results[] = array('index' => $index, 'id' => $id, 'sku' => $sku, 'status' => 'invalid', 'message' => lang('quantity must be a whole number.'));

			continue;

		}

		if ($quantity === false) {

			$results[] = array('index' => $index, 'id' => $id, 'sku' => $sku, 'status' => 'invalid', 'message' => lang(array(
				'string' => 'quantity must be between {var:1} and {var:2}.',
				'vars'   => array(-API_INVENTORY_QUANTITY_LIMIT, API_INVENTORY_QUANTITY_LIMIT)
			)));

			continue;

		}

		$location = api_inventory_location(isset($item['location']) ? $item['location'] : null);

		if ($location === null) {

			$results[] = array('index' => $index, 'id' => $id, 'sku' => $sku, 'status' => 'invalid', 'message' => lang('location is not a stock location of this site.'));

			continue;

		}

		$outcome = api_inventory_apply($id, $sku, $op, $quantity, false, $dry, $location);

		if ($outcome['status'] === 'ok') {

			$applied++;

			$entry = array(
				'index'     => $index,
				'id'        => ($outcome['inventory'] === null) ? $id : $outcome['inventory']['id'],
				'sku'       => $sku,
				'status'    => 'ok'
			);

			// Absent on a dry run rather than filled with the levels as they
			// stand: a caller that read those as the outcome would be reading the
			// state its own call did not produce.
			if ($outcome['inventory'] !== null) {

				$entry['inventory'] = $outcome['inventory'];

			}

			$results[] = $entry;

		} else {

			$results[] = array(
				'index'   => $index,
				'id'      => $id,
				'sku'     => $sku,
				'status'  => $outcome['status'],
				'message' => ($outcome['status'] === 'refused')
					? lang('The stock could not be changed at this location: the change would pass the largest quantity it can hold.')
					: (($outcome['status'] === 'not_tracked')
					? lang('This product does not track stock. Send op=set with a quantity to start tracking it.')
					: (($outcome['status'] === 'bundle')
						? lang('This product is a bundle: its stock follows the products in it. Change their stock instead.')
						: lang('Product not found.')))
			);

		}

	}

	// Every item has been resolved and judged, and nothing has been written.
	api_dry_run_stop('updated', 'inventory', array(
		'applied' => $applied,
		'total'   => count($items),
		'items'   => $results
	));

	$app = api_current_app();

	log_activity(lang(array(
		'string' => 'Stock for {var:1} of {var:2} products was changed through the API by the application {var:3} (key {var:4}).',
		'vars'   => array($applied, count($items), $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	// 200 rather than 207: the batch itself succeeded, and every item carries
	// its own outcome. A partial failure is data here, not a transport error.
	api_ok(array(
		'applied' => $applied,
		'total'   => count($items),
		'items'   => $results
	));

}

// One product, one change. Answers status 'ok' with the new stock,
// 'not_found', 'bundle' for a bundle (its stock is its components'), or
// 'not_tracked' when an adjust was aimed at a product that does not track
// stock.
//
// With stock per location a tracked product's units change at one location
// ($location, 0 for the default) through pg_stock_change(): an adjust stops
// at 0 there, as the total's GREATEST(0, ...) does, and nothing is taken from
// another location; the total follows from the product's rows.
function api_inventory_apply($id, $sku, $op, $quantity, $write_log = true, $dry = false, $location = 0) {

	if ($id <= 0 && $sku !== '') {

		$id = (int)api_value("SELECT product_id FROM product_barcodes
			WHERE barcode = '" . escape($sku) . "' LIMIT 1");

	}

	if ($id <= 0) {

		return array('status' => 'not_found');

	}

	$product = api_row("SELECT id, name, inventory, inventory_quantity FROM products WHERE id = '" . $id . "' LIMIT 1");

	if ($product === null) {

		return array('status' => 'not_found');

	}

	// A bundle's stock is a cache of its components' (includes/fn/bundles.php).
	if (pg_bundle_ids_among(array($id))) {

		return array('status' => 'bundle');

	}

	// An adjustment against a product that is not tracking stock is refused.
	//
	// A set says what the stock level is, which is a decision, and turning
	// tracking on with it is what the integrator meant. An adjust says "one
	// fewer than whatever there was" - and on an untracked product there was
	// nothing, so the arithmetic lands on zero, the flag says out of stock and
	// a gift card that was deliberately sold without a stock limit disappears
	// from the shop. Refusing says what to do instead; switching tracking on
	// quietly does not.
	if ($op !== 'set' && (int)$product['inventory'] !== 1) {

		return array('status' => 'not_tracked');

	}

	if ($op === 'set') {

		$new_quantity = ($quantity < 0) ? 0 : $quantity;

		$quantity_sql = "'" . $new_quantity . "'";

	} else {

		// The whole point of this file: the read, the arithmetic and the write
		// are one statement, and the floor at zero is inside it.
		$quantity_sql = "GREATEST(0, CAST(inventory_quantity AS SIGNED) + (" . (int)$quantity . "))";

	}

	// out_of_stock is assigned FIRST, and the order is load bearing. MySQL
	// applies the assignments in an UPDATE from left to right and a later one
	// sees the values the earlier ones wrote, so computing the flag after
	// inventory_quantity had been set would apply the adjustment a second time
	// inside the flag's own expression. Evaluated first, the expression still
	// reads the quantity as it stands.
	//
	// Tracking is switched on by a set, and only by a set: stating a stock level
	// is the integrator saying this product has countable stock, so they do not
	// have to ask for tracking separately. An adjust never switches it on - see
	// above.
	$tracking_sql = ($op === 'set') ? "inventory = '1'," : '';

	// A dry run stops on this line. Everything above it is a check - the product
	// exists, and it tracks stock unless this is a set - and everything below it
	// writes. The caller is told the item would have been applied and is given
	// no levels, because the only levels this function could offer are the ones
	// from before the change it did not make.
	if ($dry) {

		return array('status' => 'ok', 'inventory' => null);

	}

	$stock_location = null;

	if (pg_stock_has_locations()) {

		// A set switches tracking on first: the writer keeps rows only for a
		// product that tracks stock, and seeds its default row from the total.
		if ((int)$product['inventory'] !== 1) {

			api_exec("UPDATE products SET inventory = '1' WHERE id = '" . $id . "' LIMIT 1");

		}

		$location_id = $location ? (int)$location : pg_stock_default_location();

		$change = pg_stock_change_from_row($id, $location_id,
			function ($at_location) use ($op, $quantity) {
				return pg_stock_api_delta($op, $quantity, $at_location);
			},
			'api', (string)(api_current_app()['api_key'] ?? ''));

		if (!$change['ok']) {

			return array('status' => 'refused');

		}

		$stock_rows = pg_stock_rows($id);

		$stock_location = array(
			'id'       => $location_id,
			'name'     => pg_stock_location_name($location_id),
			'quantity' => isset($stock_rows[$location_id]) ? (int)$stock_rows[$location_id] : 0
		);

		api_exec("UPDATE products
			SET out_of_stock = IF(inventory_quantity <= 0, '1', '0'),
			    timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . $id . "'
			LIMIT 1");

	} else {

		api_exec("UPDATE products
			SET out_of_stock = IF((" . $quantity_sql . ") <= 0, '1', '0'),
			    inventory_quantity = " . $quantity_sql . ",
			    " . $tracking_sql . "
			    timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . $id . "'
			LIMIT 1");

	}

	$row = api_row("SELECT id, inventory, inventory_quantity, out_of_stock, timestamp
		FROM products WHERE id = '" . $id . "' LIMIT 1");

	// A batch writes one summary line instead of one line per product: the
	// activity log is a per-user list with a fixed ceiling, and two hundred
	// stock rows would push everything else out of it.
	if ($write_log) {

		$app = api_current_app();

		log_activity(lang(array(
			'string' => 'Stock for product ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
			'vars'   => array($product['name'], $app['name'], $app['api_key'])
		)), $app['owner']['username']);

	}

	$stock_event = array(
		'product_id' => (int)$row['id'],
		'quantity'   => (int)$row['inventory_quantity'],
		'out_of_stock' => ((int)$row['out_of_stock'] === 1)
	) + pg_stock_event_location_fields($stock_location);

	api_webhook_enqueue('inventory.changed', $stock_event);

	// The announcement above skips pg_announce(), whose listeners hear the
	// site's other stock writers; a product back in stock is told here.
	if (function_exists('pg_stock_notify_heard')) {
		pg_stock_notify_heard('inventory.changed', $stock_event);
	}

	// Crossing the low-stock line, announced once.
	//
	// On the crossing rather than on every write: a product sitting at two with
	// a threshold of five would otherwise produce an event on every sale, and a
	// reorder notice that arrives four times a day stops being read. The store
	// has to be running a threshold at all, and the product has to track stock -
	// a gift card that was deliberately sold without a stock limit has no low
	// to cross.
	$threshold = (defined('ECOMMERCE_LOW_STOCK_THRESHOLD') && ((int)ECOMMERCE_LOW_STOCK_THRESHOLD > 0))
		? (int)ECOMMERCE_LOW_STOCK_THRESHOLD
		: 0;

	if (($threshold > 0) && ((int)$row['inventory'] === 1)
		&& ((int)$row['inventory_quantity'] <= $threshold)
		&& ((int)$product['inventory_quantity'] > $threshold)) {

		api_webhook_enqueue('stock.low', array(
			'product_id' => (int)$row['id'],
			'name'       => $product['name'],
			'quantity'   => (int)$row['inventory_quantity'],
			'threshold'  => $threshold
		) + pg_stock_event_location_fields($stock_location));

	}

	// The same fact, going the other way. A shop whose stock is driven by one
	// marketplace's API has to have the others told about it, and this is the
	// point where that is known.
	if (function_exists('pg_marketplace_product_changed')) {

		pg_marketplace_product_changed((int)$row['id']);

	}

	return array(
		'status' => 'ok',
		'inventory' => array(
			'id'           => (int)$row['id'],
			'tracked'      => ((int)$row['inventory'] === 1),
			'quantity'     => (int)$row['inventory_quantity'],
			'out_of_stock' => ((int)$row['out_of_stock'] === 1),
			'updated_at'   => api_time($row['timestamp'])
		) + (($stock_location === null) ? array() : array(
			'location_id'       => $stock_location['id'],
			'location_quantity' => $stock_location['quantity']
		))
	);

}

// Stock per location (includes/fn/stock_locations.php). A product's quantity
// is the sum of its locations' units; the endpoints below read the locations,
// the units at each, and move units between two of them.

// One stock location as the API shows it.
function api_inventory_location_present(array $row) {

	return array(
		'id'         => (int)$row['id'],
		'name'       => (string)$row['name'],
		'address'    => (string)$row['address'],
		'enabled'    => ((int)$row['enabled'] === 1),
		'is_default' => ((int)$row['is_default'] === 1),
		'sort_order' => (int)$row['sort_order']
	);

}

// One product's stock level. $rows is location id => units (the product's
// product_stock rows), $location_ids the site's locations in their order,
// $only one location to show (0: all). A product without rows holds its total
// at the default location, as the checkout counts it (pg_stock_available_at()).
function api_inventory_level_present(array $product, array $rows, array $location_ids, $default_id, $only) {

	$locations = array();

	foreach (($only ? array((int)$only) : $location_ids) as $location_id) {

		$locations[] = array(
			'location_id' => (int)$location_id,
			'quantity'    => pg_stock_available_at($rows, $location_id, $default_id, $product['inventory_quantity'])
		);

	}

	return array(
		'product_id'   => (int)$product['id'],
		'name'         => (string)$product['name'],
		'quantity'     => (int)$product['inventory_quantity'],
		'out_of_stock' => ((int)$product['out_of_stock'] === 1),
		'locations'    => $locations
	);

}

function api_inventory_locations_list($params) {

	$out = array();

	foreach (pg_stock_locations() as $row) {

		if (isset($params['enabled']) && (((int)$row['enabled'] === 1) !== (bool)$params['enabled'])) {

			continue;

		}

		$out[] = api_inventory_location_present($row);

	}

	api_ok_list($out, max(1, count($out)), null);

}

// The conditions of GET /inventory without its cursor: products that track
// stock, out of the Recycle Bin when the catalogue has one ($bin), and the
// product and stock filters. Pure.
function api_inventory_list_where($params, $location, $default_id, $bin) {

	$where = array("products.inventory = '1'");

	// A product in the catalogue Recycle Bin is deleted as far as the shop is
	// concerned: GET /products leaves it out, so this list does too, or a
	// stock sync would keep offering a product the shop no longer sells.
	if ($bin) {

		$where[] = "products.recycled = '0'";

	}

	if (!empty($params['product_id'])) {

		$where[] = "products.id = '" . (int)$params['product_id'] . "'";

	}

	if (isset($params['in_stock'])) {

		if ($location) {

			// Units at the location: its row, or the whole total at the
			// default for a product that has no rows yet.
			$held = "(EXISTS (SELECT 1 FROM product_stock AS held
					WHERE held.product_id = products.id AND held.location_id = '" . (int)$location . "' AND held.quantity > 0)"
				. (((int)$location === (int)$default_id)
					? " OR (products.inventory_quantity > 0 AND NOT EXISTS (SELECT 1 FROM product_stock AS seeded WHERE seeded.product_id = products.id))"
					: '')
				. ")";

			$where[] = $params['in_stock'] ? $held : "NOT " . $held;

		} else {

			$where[] = $params['in_stock'] ? "products.inventory_quantity > 0" : "products.inventory_quantity <= 0";

		}

	}

	return $where;

}

function api_inventory_list($params) {

	$location = api_inventory_location(isset($params['location']) ? $params['location'] : null);

	if ($location === null) {

		api_fail(422, 'location', lang('location is not a stock location of this site.'), 'location');

	}

	$location_ids = array();

	foreach (pg_stock_locations() as $row) {

		$location_ids[] = (int)$row['id'];

	}

	$default_id = pg_stock_default_location();

	$where = api_inventory_list_where($params, $location, $default_id, api_catalog_bin_columns());

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "products.id > '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$products = api_rows("SELECT products.id, products.name, products.inventory_quantity, products.out_of_stock
		FROM products
		WHERE " . implode(' AND ', $where) . "
		ORDER BY products.id
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($products) > $limit) {

		array_pop($products);

		$last = end($products);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	// The page's rows in one read, not one per product.
	$rows = array();

	if ($products && $location_ids) {

		$ids = array();

		foreach ($products as $product) {

			$ids[] = (int)$product['id'];

		}

		foreach (api_rows("SELECT product_id, location_id, quantity FROM product_stock
			WHERE product_id IN (" . implode(',', $ids) . ")") as $row) {

			$rows[(int)$row['product_id']][(int)$row['location_id']] = (int)$row['quantity'];

		}

	}

	$out = array();

	foreach ($products as $product) {

		$out[] = api_inventory_level_present($product,
			isset($rows[(int)$product['id']]) ? $rows[(int)$product['id']] : array(),
			$location_ids, $default_id, $location);

	}

	api_ok_list($out, $limit, $next);

}

// How a refused transfer answers, from pg_stock_transfer()'s and
// pg_stock_change()'s error words: status, code, field.
function api_inventory_transfer_refusal($error) {

	switch ($error) {

		case 'quantity':
			return array(422, 'validation_failed', 'quantity');

		case 'from':
			return array(422, 'location', 'from');

		case 'to':
		case 'location':
			return array(422, 'location', 'to');

		case 'same':
			return array(422, 'validation_failed', 'to');

		case 'to_disabled':
			return array(409, 'location_disabled', 'to');

		case 'not_enough':
			return array(409, 'not_enough', 'quantity');

		case 'untracked':
			return array(409, 'not_tracked', null);

		case 'no_locations':
			return array(409, 'no_locations', null);

	}

	return array(409, 'refused', null);

}

function api_inventory_transfer_fail($error) {

	list($status, $code, $field) = api_inventory_transfer_refusal($error);

	api_fail($status, $code, pg_stock_transfer_error_text($error), $field);

}

function api_inventory_transfer($params) {

	$product_id = (int)$params['product_id'];

	$product = api_row("SELECT id, name, inventory, inventory_quantity FROM products WHERE id = '" . $product_id . "' LIMIT 1");

	if ($product === null) {

		api_fail_not_found(lang('Product'));

	}

	if (pg_bundle_ids_among(array($product_id))) {

		api_fail(409, 'bundle', lang('This product is a bundle: its stock follows the products in it. Change their stock instead.'));

	}

	if (!pg_stock_has_locations()) {

		api_inventory_transfer_fail('no_locations');

	}

	if ((int)$product['inventory'] !== 1) {

		api_inventory_transfer_fail('untracked');

	}

	$check = pg_stock_transfer_check($params['from'], $params['to'], $params['quantity'], pg_stock_locations());

	if (!$check['ok']) {

		api_inventory_transfer_fail($check['error']);

	}

	// What the location holds now; the writer checks it again under its lock,
	// so a sale in between is still refused there.
	$held = pg_stock_available_at(pg_stock_rows($product_id), $check['from'], pg_stock_default_location(), $product['inventory_quantity']);

	if ($held < $check['quantity']) {

		api_inventory_transfer_fail('not_enough');

	}

	api_dry_run_stop('updated', 'inventory', array(
		'product_id' => $product_id,
		'from'       => $check['from'],
		'to'         => $check['to'],
		'quantity'   => $check['quantity']
	));

	$app = api_current_app();

	$moved = pg_stock_transfer($product_id, $check['from'], $check['to'], $check['quantity'],
		isset($params['note']) ? (string)$params['note'] : '',
		array('user_id' => (int)$app['owner']['id']));

	if (!$moved['ok']) {

		api_inventory_transfer_fail($moved['error']);

	}

	log_activity(lang(array(
		'string' => 'Stock of product ({var:1}) was moved between locations through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($product['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array(
		'product_id' => $product_id,
		'quantity'   => $check['quantity'],
		'from'       => array('location_id' => $check['from'], 'quantity' => (int)$moved['from']['after']),
		'to'         => array('location_id' => $check['to'], 'quantity' => (int)$moved['to']['after']),
		'total'      => (int)$moved['to']['total'],
		'ref'        => $moved['ref']
	));

}

function api_stock_location_schema() {

	return array(
		'id'         => 'integer',
		'name'       => 'string',
		'address'    => 'string',
		'enabled'    => 'boolean',
		'is_default' => 'boolean',
		'sort_order' => 'integer'
	);

}

function api_stock_level_schema() {

	return array(
		'product_id'   => 'integer',
		'name'         => 'string',
		'quantity'     => 'integer',
		'out_of_stock' => 'boolean',
		'locations'    => array(array(
			'location_id' => 'integer',
			'quantity'    => 'integer'
		))
	);

}

function api_stock_transfer_schema() {

	return array(
		'product_id' => 'integer',
		'quantity'   => 'integer',
		'from'       => array('location_id' => 'integer', 'quantity' => 'integer'),
		'to'         => array('location_id' => 'integer', 'quantity' => 'integer'),
		'total'      => 'integer',
		'ref'        => 'string'
	);

}

// What the two stock endpoints answer with, declared for the OpenAPI document.
// One product returns the block itself; the batch returns a count and one
// outcome per item, because a partial failure is data here and not an error.
function api_inventory_schema() {

	return array(
		'id'           => 'integer',
		'tracked'      => 'boolean',
		'quantity'     => 'integer',
		'out_of_stock' => 'boolean',
		'updated_at'   => 'string?',
		'location_id'       => 'integer?',
		'location_quantity' => 'integer?'
	);

}

function api_inventory_batch_schema() {

	return array(
		'applied' => 'integer',
		'total'   => 'integer',
		'items'   => array(array(
			'index'     => 'integer',
			'id'        => 'integer',
			'sku'       => 'string',
			'status'    => 'string',
			'message'   => 'string',
			'inventory' => 'Inventory'
		))
	);

}
