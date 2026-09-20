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

if (!defined('PG_API_ENTRY')) {
	exit;
}

require_once(dirname(dirname(__FILE__)) . '/outbound/webhooks.php');

function api_inventory_write($params) {

	$result = api_inventory_apply((int)$params['id'], '', $params['op'], (int)$params['quantity'], true, api_dry_run_requested());

	if ($result['status'] === 'not_tracked') {

		api_fail(409, 'not_tracked', lang('This product does not track stock. Send op=set with a quantity to start tracking it.'));

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
		'quantity' => (int)$params['quantity']
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

		if (!isset($item['quantity']) || !is_numeric($item['quantity'])) {

			$results[] = array('index' => $index, 'id' => $id, 'sku' => $sku, 'status' => 'invalid', 'message' => lang('quantity must be a whole number.'));

			continue;

		}

		$outcome = api_inventory_apply($id, $sku, $op, (int)$item['quantity'], false, $dry);

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
				'message' => ($outcome['status'] === 'not_tracked')
					? lang('This product does not track stock. Send op=set with a quantity to start tracking it.')
					: lang('Product not found.')
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

// One product, one change. Answers status 'ok' with the new stock, 'not_found',
// or 'not_tracked' when an adjust was aimed at a product that does not track
// stock.
function api_inventory_apply($id, $sku, $op, $quantity, $write_log = true, $dry = false) {

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

	api_exec("UPDATE products
		SET out_of_stock = IF((" . $quantity_sql . ") <= 0, '1', '0'),
		    inventory_quantity = " . $quantity_sql . ",
		    " . $tracking_sql . "
		    timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . $id . "'
		LIMIT 1");

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

	api_webhook_enqueue('inventory.changed', array(
		'product_id' => (int)$row['id'],
		'quantity'   => (int)$row['inventory_quantity'],
		'out_of_stock' => ((int)$row['out_of_stock'] === 1)
	));

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
		));

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
		)
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
		'updated_at'   => 'string?'
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
