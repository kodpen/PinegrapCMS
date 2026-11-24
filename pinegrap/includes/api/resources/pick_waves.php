<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Pick and pack over the external API: the pick waves and a wave's pick
// list as Orders > Pick waves shows them, an order's packs as the Pack
// screen counts them, and a product's shelf address. The waves and packs
// are read only: making, ticking, scanning and closing stay on the
// screens. Nothing here carries a price, a total or a customer's name or
// address.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/pack.php');

// A wave's status as the API names it.
function api_pick_wave_statuses() {

	return array(
		'open'   => PG_WAVE_OPEN,
		'picked' => PG_WAVE_PICKED,
		'closed' => PG_WAVE_CLOSED
	);

}

// A pack's status as the API names it; waiting is an address no scan has
// started yet (no order_packs row).
function api_pick_pack_status_name($status) {

	$names = array(PG_PACK_OPEN => 'open', PG_PACK_PACKED => 'packed', PG_PACK_SHORT => 'short');

	return ($status !== null && isset($names[(int)$status])) ? $names[(int)$status] : 'waiting';

}

// A day kept as the unix time of its start, as Y-m-d; null for none.
function api_pick_day($unix) {

	return ((int)$unix > 0) ? date('Y-m-d', (int)$unix) : null;

}

// GET /pick-waves
function api_pick_waves_list($params) {

	$limit = (int)$params['limit'];

	$before = 0;

	if (isset($params['cursor']) && ($params['cursor'] !== '')) {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$before = max(1, (int)$cursor['i']);

	}

	$statuses = api_pick_wave_statuses();

	$status = (isset($params['status']) && isset($statuses[$params['status']])) ? $statuses[$params['status']] : null;

	// Before the update that adds the tables there are no waves to list.
	$rows = pg_pick_waves_ready() ? pg_pick_wave_list($status, $before, $limit + 1) : array();

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$next = api_cursor_encode(0, (int)$rows[count($rows) - 1]['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_pick_wave_present($row);

	}

	api_ok_list($out, $limit, $next);

}

// GET /pick-waves/{id}
function api_pick_waves_get($params) {

	$wave = pg_pick_waves_ready() ? pg_pick_wave((int)$params['id']) : null;

	if ($wave === null) {

		api_fail_not_found(lang('Pick wave'));

	}

	// The leavers are synced first, so the counts read what the screen reads.
	$addresses = pg_pick_wave_addresses($wave['id']);

	$ticked = pg_pick_list_ticked(pg_pick_wave_lines($wave['id']), $wave['picked']);

	$row = $wave;

	$row['addresses'] = 0;

	$row['units'] = 0;

	$row['packed'] = 0;

	foreach ($addresses as $address) {

		if ((int)$address['removed_at'] > 0) {

			continue;

		}

		$row['addresses']++;

		$row['units'] += (int)$address['units'];

		$row['packed'] += ((int)$address['pack_status'] === PG_PACK_PACKED) ? 1 : 0;

	}

	$row['created_by_name'] = ((int)$wave['created_by'] > 0)
		? (string)db_value("SELECT user_username FROM user WHERE user_id = '" . (int)$wave['created_by'] . "'")
		: '';

	api_ok(api_pick_wave_detail_present($row, $ticked, $addresses));

}

// One wave of GET /pick-waves. Pure: $row is a row of pg_pick_wave_list().
function api_pick_wave_present($row) {

	$name = array_search((int)$row['status'], api_pick_wave_statuses(), true);

	return array(
		'id'              => (int)$row['id'],
		'name'            => ((string)$row['name'] !== '') ? (string)$row['name'] : null,
		'status'          => ($name === false) ? 'open' : (string)$name,
		'location_id'     => ((int)$row['location_id'] > 0) ? (int)$row['location_id'] : null,
		'single_item'     => !empty($row['single_item']),
		'created_by'      => ((int)$row['created_by'] > 0) ? (int)$row['created_by'] : null,
		'created_by_name' => ((string)($row['created_by_name'] ?? '') !== '') ? (string)$row['created_by_name'] : null,
		'created_at'      => api_time($row['created_at']),
		'closed_at'       => api_time($row['closed_at'] ?? 0),
		'addresses'       => (int)$row['addresses'],
		'units'           => (int)$row['units'],
		'packed'          => (int)$row['packed']
	);

}

// What api_pick_wave_present() answers with, declared for the OpenAPI
// document.
function api_pick_wave_schema() {

	return array(
		'id'              => 'integer',
		'name'            => 'string?',
		'status'          => 'string',
		'location_id'     => 'integer?',
		'single_item'     => 'boolean',
		'created_by'      => 'integer?',
		'created_by_name' => 'string?',
		'created_at'      => 'datetime?',
		'closed_at'       => 'datetime?',
		'addresses'       => 'integer',
		'units'           => 'integer',
		'packed'          => 'integer'
	);

}

// One wave of GET /pick-waves/{id}: the wave, its pick list and its
// addresses. Pure: $row as for api_pick_wave_present(), $ticked
// pg_pick_list_ticked(), $addresses pg_pick_wave_addresses().
function api_pick_wave_detail_present($row, $ticked, $addresses) {

	$lines = array();

	foreach ($ticked['lines'] as $line) {

		$lines[] = array(
			'product_id'  => ((int)$line['product_id'] > 0) ? (int)$line['product_id'] : null,
			'location_id' => ((int)$line['location_id'] > 0) ? (int)$line['location_id'] : null,
			'shelf'       => ((string)$line['bin'] !== '') ? (string)$line['bin'] : null,
			'code'        => (string)$line['code'],
			'name'        => (string)$line['name'],
			'options'     => array_values(array_map('strval', (array)$line['options'])),
			'units'       => (int)$line['units'],
			'addresses'   => (int)$line['addresses'],
			'picked'      => !empty($line['picked'])
		);

	}

	$out = array();

	foreach ($addresses as $address) {

		$out[] = array(
			'ship_to_id'   => (int)$address['ship_to_id'],
			'order_id'     => (int)$address['order_id'],
			'order_number' => ((string)$address['order_number'] !== '') ? (string)$address['order_number'] : null,
			'ship_by'      => api_pick_day($address['ship_by']),
			'units'        => (int)$address['units'],
			'pack_status'  => api_pick_pack_status_name(((int)$address['pack_status'] < 0) ? null : (int)$address['pack_status']),
			'removed_at'   => api_time($address['removed_at'])
		);

	}

	return api_pick_wave_present($row) + array(
		'picked_lines' => (int)$ticked['done'],
		'total_lines'  => (int)$ticked['total'],
		'lines'        => $lines,
		'orders'       => $out
	);

}

// What api_pick_wave_detail_present() answers with.
function api_pick_wave_detail_schema() {

	return api_pick_wave_schema() + array(
		'picked_lines' => 'integer',
		'total_lines'  => 'integer',
		'lines'        => 'PickListLine[]',
		'orders'       => 'PickWaveOrder[]'
	);

}

// One line of a wave's pick list.
function api_pick_list_line_schema() {

	return array(
		'product_id'  => 'integer?',
		'location_id' => 'integer?',
		'shelf'       => 'string?',
		'code'        => 'string',
		'name'        => 'string',
		'options'     => 'string[]',
		'units'       => 'integer',
		'addresses'   => 'integer',
		'picked'      => 'boolean'
	);

}

// One address of a wave.
function api_pick_wave_order_schema() {

	return array(
		'ship_to_id'   => 'integer',
		'order_id'     => 'integer',
		'order_number' => 'string?',
		'ship_by'      => 'date?',
		'units'        => 'integer',
		'pack_status'  => 'string',
		'removed_at'   => 'datetime?'
	);

}

// GET /orders/{id}/packs
function api_orders_packs($params) {

	$id = (int)$params['id'];

	if (api_row("SELECT id FROM orders WHERE id = '" . $id . "' LIMIT 1") === null) {

		api_fail_not_found(lang('Order'));

	}

	$out = array();

	// Before the update that adds the tables no address has a pack.
	if (!pg_pick_waves_ready()) {

		api_ok_list($out, 1, null);

	}

	$ship_tos = db_items(
		"SELECT ship_tos.id,
			(SELECT wave_row.wave_id FROM pick_wave_orders AS wave_row
				INNER JOIN pick_waves ON pick_waves.id = wave_row.wave_id
				WHERE wave_row.ship_to_id = ship_tos.id AND wave_row.removed_at = 0 AND pick_waves.status <> '" . PG_WAVE_CLOSED . "'
				ORDER BY wave_row.wave_id DESC LIMIT 1) AS wave_id
		FROM ship_tos
		WHERE ship_tos.order_id = '" . $id . "'
		ORDER BY ship_tos.id");

	foreach ($ship_tos as $ship_to) {

		$pack = pg_pack_read(array('ship_to_id' => (int)$ship_to['id'], 'order_id' => $id, 'wave_id' => (int)$ship_to['wave_id']));

		// An address with nothing to send has nothing to pack.
		if (!$pack['lines']) {

			continue;

		}

		$out[] = api_order_pack_present($pack);

	}

	api_ok_list($out, max(1, count($out)), null);

}

// One address's pack. Pure: $pack is pg_pack_read()'s answer.
function api_order_pack_present($pack) {

	$started = ((int)$pack['id'] > 0);

	$missing = array();

	foreach (pg_pack_missing($pack['lines']) as $product_id => $units) {

		$missing[] = api_order_pack_missing_present($product_id, $units);

	}

	return array(
		'ship_to_id' => (int)$pack['ship_to_id'],
		'order_id'   => (int)$pack['order_id'],
		'wave_id'    => ((int)$pack['wave_id'] > 0) ? (int)$pack['wave_id'] : null,
		'status'     => api_pick_pack_status_name($started ? (int)$pack['status'] : null),
		'packer_id'  => ((int)$pack['packer_id'] > 0) ? (int)$pack['packer_id'] : null,
		'started_at' => api_time($pack['started_at']),
		'packed_at'  => api_time($pack['packed_at']),
		'short_at'   => api_time($pack['short_at'] ?? 0),
		'units'      => (int)$pack['units'],
		'scanned'    => (int)$pack['scanned'],
		'refused'    => (int)$pack['refused'],
		'lines'      => array_map('api_order_pack_line_present', $pack['lines']),
		'missing'    => $missing
	);

}

// One line of a pack. Pure: $line is a line of pg_pack_lines_build().
function api_order_pack_line_present($line) {

	return array(
		'product_id' => ((int)$line['product_id'] > 0) ? (int)$line['product_id'] : null,
		'code'       => (string)$line['code'],
		'name'       => (string)$line['name'],
		'options'    => array_values(array_map('strval', (array)$line['options'])),
		'units'      => (int)$line['quantity'],
		'scanned'    => (int)$line['scanned']
	);

}

// A product a pack misses, and how many units. Pure.
function api_order_pack_missing_present($product_id, $units) {

	return array('product_id' => ((int)$product_id > 0) ? (int)$product_id : null, 'units' => (int)$units);

}

// What api_order_pack_present() answers with.
function api_order_pack_schema() {

	return array(
		'ship_to_id' => 'integer',
		'order_id'   => 'integer',
		'wave_id'    => 'integer?',
		'status'     => 'string',
		'packer_id'  => 'integer?',
		'started_at' => 'datetime?',
		'packed_at'  => 'datetime?',
		'short_at'   => 'datetime?',
		'units'      => 'integer',
		'scanned'    => 'integer',
		'refused'    => 'integer',
		'lines'      => 'OrderPackLine[]',
		'missing'    => 'OrderPackMissing[]'
	);

}

// A product a short pack misses, and how many units.
function api_order_pack_missing_schema() {

	return array(
		'product_id' => 'integer?',
		'units'      => 'integer'
	);

}

// One line of a pack.
function api_order_pack_line_schema() {

	return array(
		'product_id' => 'integer?',
		'code'       => 'string',
		'name'       => 'string',
		'options'    => 'string[]',
		'units'      => 'integer',
		'scanned'    => 'integer'
	);

}

// POST /products/{id}/shelf (PUT too)
function api_products_shelf($params) {

	$product_id = (int)$params['id'];

	$location = api_inventory_location(isset($params['location']) ? $params['location'] : null);

	if ($location === null) {

		api_fail(422, 'location', lang('location is not a stock location of this site.'));

	}

	$shelf = pg_bin_clean(isset($params['shelf']) ? $params['shelf'] : '');

	if ($shelf === false) {

		api_fail(422, 'shelf', lang('shelf is not a shelf address this site can keep.'));

	}

	if (api_row("SELECT id FROM products WHERE id = '" . $product_id . "' LIMIT 1") === null) {

		api_fail_not_found(lang('Product'));

	}

	if (pg_bundle_ids_among(array($product_id))) {

		api_fail(409, 'bundle', lang('This product is a bundle: it has no shelf address. Give the products in it theirs.'));

	}

	if (!pg_bins_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));

	}

	$key = pg_bin_location_key($location);

	api_dry_run_stop('updated', 'shelf', array('id' => $product_id, 'location' => $key, 'shelf' => $shelf));

	pg_bins_write($product_id, array($key => $shelf));

	api_ok(api_product_shelf_present($product_id, $key, $shelf));

}

// The answer of POST /products/{id}/shelf. Pure.
function api_product_shelf_present($product_id, $location, $shelf) {

	return array(
		'product_id' => (int)$product_id,
		'location'   => (int)$location,
		'shelf'      => ((string)$shelf !== '') ? (string)$shelf : null
	);

}

// What api_product_shelf_present() answers with.
function api_product_shelf_schema() {

	return array(
		'product_id' => 'integer',
		'location'   => 'integer',
		'shelf'      => 'string?'
	);

}
