<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// What the store computes an order with: its shipping methods and the zones
// they ship to, the tax zones, the countries and states an address can name,
// and the currencies a visitor can see prices in.
//
// Read only. These are set up once on their own screens, and an integration
// reads them to quote shipping, map a marketplace's carrier or country onto
// the store's, or show a price the way the store would.
//
// Every list is small (a store has a handful of methods and zones, and the
// country list is the world's), so each comes back as one page.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

function api_store_days() {

	return array('mon' => 'monday', 'tue' => 'tuesday', 'wed' => 'wednesday', 'thu' => 'thursday', 'fri' => 'friday', 'sat' => 'saturday', 'sun' => 'sunday');

}

// The table writes "always" as the widest datetime it holds.
function api_store_window_time($value) {

	$value = (string)$value;

	if (($value === '') || (strpos($value, '0000-00-00') === 0) || (strpos($value, '1000-01-01') === 0) || (strpos($value, '9999-12-31') === 0)) {

		return null;

	}

	$unix = strtotime($value);

	return ($unix === false) ? null : date('c', $unix);

}

// A cut-off time of midnight means the whole day.
function api_store_cutoff($value) {

	$value = (string)$value;

	return (($value === '') || ($value === '00:00:00')) ? null : substr($value, 0, 5);

}

// The countries and states a set of zones covers, read once for all of them.
// $table is zones or tax_zones; the cross tables follow its name.
function api_store_zone_areas($table, $ids) {

	$areas = array();

	$ids = array_values(array_unique(array_map('intval', $ids)));

	if (empty($ids)) {

		return $areas;

	}

	$key = ($table === 'tax_zones') ? 'tax_zone_id' : 'zone_id';

	$in = implode(',', $ids);

	foreach (api_rows("SELECT x." . $key . " AS zone_id, countries.code
		FROM " . $table . "_countries_xref AS x
		INNER JOIN countries ON countries.id = x.country_id
		WHERE x." . $key . " IN (" . $in . ")
		ORDER BY countries.code ASC") as $row) {

		$areas[(int)$row['zone_id']]['countries'][] = (string)$row['code'];

	}

	foreach (api_rows("SELECT x." . $key . " AS zone_id, states.code, states.name, countries.code AS country_code
		FROM " . $table . "_states_xref AS x
		INNER JOIN states ON states.id = x.state_id
		LEFT JOIN countries ON countries.id = states.country_id
		WHERE x." . $key . " IN (" . $in . ")
		ORDER BY countries.code ASC, states.code ASC") as $row) {

		$areas[(int)$row['zone_id']]['states'][] = array(
			'country_code' => ($row['country_code'] !== null) ? (string)$row['country_code'] : null,
			'code'         => (string)$row['code'],
			'name'         => (string)$row['name']
		);

	}

	return $areas;

}

function api_store_state_schema() {

	return array(
		'country_code' => 'string?',
		'code'         => 'string',
		'name'         => 'string'
	);

}

/* ----- Shipping methods ---------------------------------------------------- */

// The base rate can step down (or up) as the recipient's subtotal grows: each
// tier applies from its subtotal on, up to the next.
function api_store_shipping_method_base_rates($row) {

	$tiers = array(array('from_subtotal' => 0, 'rate' => api_money($row['base_rate'])));

	if ((int)$row['variable_base_rate'] !== 1) {

		return $tiers;

	}

	foreach (array(2, 3, 4) as $tier) {

		if ((int)$row['base_rate_' . $tier . '_subtotal'] <= 0) {

			break;

		}

		$tiers[] = array('from_subtotal' => api_money($row['base_rate_' . $tier . '_subtotal']), 'rate' => api_money($row['base_rate_' . $tier]));

	}

	return $tiers;

}

// The days a method is offered on, each until its cut-off time (null: all day).
function api_store_shipping_method_offered_on($row) {

	$days = array();

	foreach (api_store_days() as $short => $long) {

		if ((int)$row['available_on_' . $long] === 1) {

			$days[] = array('day' => $short, 'until' => api_store_cutoff($row['available_on_' . $long . '_cutoff_time']));

		}

	}

	return $days;

}

// The weekdays a column family (handle_, ship_) is switched on for.
function api_store_shipping_method_days($row, $prefix) {

	$days = array();

	foreach (array_keys(api_store_days()) as $short) {

		if ((int)$row[$prefix . $short] === 1) {

			$days[] = $short;

		}

	}

	return $days;

}

function api_store_shipping_method_present($row, $zone_ids = array()) {

	return array(
		'id'                => (int)$row['id'],
		'name'              => (string)$row['name'],
		'description'       => (string)$row['description'],
		'code'              => (string)$row['code'],
		'enabled'           => ((string)$row['status'] === 'enabled'),
		// Offered to the store's own staff only, never to a visitor.
		'protected'         => ((int)$row['protected'] === 1),
		'available_from'    => api_store_window_time($row['start_time']),
		'available_until'   => api_store_window_time($row['end_time']),
		'offered_on'        => api_store_shipping_method_offered_on($row),
		'street_address'    => ((int)$row['street_address'] === 1),
		'po_box'            => ((int)$row['po_box'] === 1),
		'base_rates'        => api_store_shipping_method_base_rates($row),
		'primary_weight_rate'   => api_money($row['primary_weight_rate']),
		'secondary_weight_rate' => api_money($row['secondary_weight_rate']),
		'item_rate'         => api_money($row['item_rate']),
		'first_item_excluded' => array(
			'primary_weight'   => ((int)$row['primary_weight_rate_first_item_excluded'] === 1),
			'secondary_weight' => ((int)$row['secondary_weight_rate_first_item_excluded'] === 1),
			'item'             => ((int)$row['item_rate_first_item_excluded'] === 1)
		),
		'realtime_service'  => ((string)$row['service'] !== '') ? (string)$row['service'] : null,
		'realtime_rate'     => ((int)$row['realtime_rate'] === 1),
		'handling_days'     => (int)$row['handle_days'],
		'handling_on'       => api_store_shipping_method_days($row, 'handle_'),
		'transit_days'      => (int)$row['base_transit_days'],
		'transit_on'        => api_store_shipping_method_days($row, 'ship_'),
		'transit_on_saturday' => ((int)$row['transit_on_saturday'] === 1),
		'transit_on_sunday' => ((int)$row['transit_on_sunday'] === 1),
		'adjust_transit_for_country' => ((int)$row['adjust_transit'] === 1),
		'end_of_day'        => api_store_cutoff($row['end_of_day']),
		'carrier_title'     => isset($row['carrier_title']) ? (string)$row['carrier_title'] : '',
		'carrier_tax_number' => isset($row['carrier_vkn']) ? (string)$row['carrier_vkn'] : '',
		'zone_ids'          => $zone_ids,
		'updated_at'        => api_time($row['timestamp'])
	);

}

function api_store_shipping_method_schema() {

	return array(
		'id'                => 'integer',
		'name'              => 'string',
		'description'       => 'string',
		'code'              => 'string',
		'enabled'           => 'boolean',
		'protected'         => 'boolean',
		'available_from'    => 'string?',
		'available_until'   => 'string?',
		'offered_on'        => array(array('day' => 'string', 'until' => 'string?')),
		'street_address'    => 'boolean',
		'po_box'            => 'boolean',
		'base_rates'        => array(array('from_subtotal' => 'integer', 'rate' => 'integer')),
		'primary_weight_rate'   => 'integer',
		'secondary_weight_rate' => 'integer',
		'item_rate'         => 'integer',
		'first_item_excluded' => array(
			'primary_weight'   => 'boolean',
			'secondary_weight' => 'boolean',
			'item'             => 'boolean'
		),
		'realtime_service'  => 'string?',
		'realtime_rate'     => 'boolean',
		'handling_days'     => 'integer',
		'handling_on'       => 'string[]',
		'transit_days'      => 'integer',
		'transit_on'        => 'string[]',
		'transit_on_saturday' => 'boolean',
		'transit_on_sunday' => 'boolean',
		'adjust_transit_for_country' => 'boolean',
		'end_of_day'        => 'string?',
		'carrier_title'     => 'string',
		'carrier_tax_number' => 'string',
		'zone_ids'          => 'integer[]',
		'updated_at'        => 'string?'
	);

}

function api_store_shipping_method_zone_ids($ids) {

	$zones = array();

	$ids = array_values(array_unique(array_map('intval', $ids)));

	if (empty($ids)) {

		return $zones;

	}

	foreach (api_rows("SELECT shipping_method_id, zone_id FROM shipping_methods_zones_xref
		WHERE shipping_method_id IN (" . implode(',', $ids) . ")
		ORDER BY shipping_method_id ASC, zone_id ASC") as $row) {

		$zones[(int)$row['shipping_method_id']][] = (int)$row['zone_id'];

	}

	return $zones;

}

function api_store_shipping_methods_list($params) {

	$where = '';

	if (isset($params['enabled'])) {

		$where = " WHERE status = '" . ($params['enabled'] ? 'enabled' : 'disabled') . "'";

	}

	$rows = api_rows("SELECT * FROM shipping_methods" . $where . " ORDER BY name ASC, id ASC");

	$zones = api_store_shipping_method_zone_ids(array_map(function ($row) { return $row['id']; }, $rows));

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_store_shipping_method_present($row, isset($zones[(int)$row['id']]) ? $zones[(int)$row['id']] : array());

	}

	api_ok_list($out, max(1, count($out)), null);

}

function api_store_shipping_methods_get($params) {

	$id = (int)$params['id'];

	$row = api_row("SELECT * FROM shipping_methods WHERE id = '" . $id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Shipping Method'));

	}

	$zones = api_store_shipping_method_zone_ids(array($id));

	api_ok(api_store_shipping_method_present($row, isset($zones[$id]) ? $zones[$id] : array()));

}

/* ----- Zones --------------------------------------------------------------- */

function api_store_zone_present($row, $areas = array()) {

	$id = (int)$row['id'];

	return array(
		'id'                    => $id,
		'name'                  => (string)$row['name'],
		// Added to what the shipping method charges for an address in the zone.
		'base_rate'             => api_money($row['base_rate']),
		'primary_weight_rate'   => api_money($row['primary_weight_rate']),
		'secondary_weight_rate' => api_money($row['secondary_weight_rate']),
		'item_rate'             => api_money($row['item_rate']),
		'country_codes'         => isset($areas[$id]['countries']) ? $areas[$id]['countries'] : array(),
		'states'                => isset($areas[$id]['states']) ? $areas[$id]['states'] : array(),
		'updated_at'            => api_time($row['timestamp'])
	);

}

function api_store_zone_schema() {

	return array(
		'id'                    => 'integer',
		'name'                  => 'string',
		'base_rate'             => 'integer',
		'primary_weight_rate'   => 'integer',
		'secondary_weight_rate' => 'integer',
		'item_rate'             => 'integer',
		'country_codes'         => 'string[]',
		'states'                => 'StoreState[]',
		'updated_at'            => 'string?'
	);

}

function api_store_zones_list($params) {

	$rows = api_rows("SELECT * FROM zones ORDER BY name ASC, id ASC");

	$areas = api_store_zone_areas('zones', array_map(function ($row) { return $row['id']; }, $rows));

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_store_zone_present($row, $areas);

	}

	api_ok_list($out, max(1, count($out)), null);

}

/* ----- Tax zones ----------------------------------------------------------- */

function api_store_tax_zone_present($row, $areas = array()) {

	$id = (int)$row['id'];

	return array(
		'id'            => $id,
		'name'          => (string)$row['name'],
		// A percentage: 18 is 18%.
		'rate'          => (float)$row['tax_rate'],
		'country_codes' => isset($areas[$id]['countries']) ? $areas[$id]['countries'] : array(),
		'states'        => isset($areas[$id]['states']) ? $areas[$id]['states'] : array(),
		'updated_at'    => api_time($row['timestamp'])
	);

}

function api_store_tax_zone_schema() {

	return array(
		'id'            => 'integer',
		'name'          => 'string',
		'rate'          => 'number',
		'country_codes' => 'string[]',
		'states'        => 'StoreState[]',
		'updated_at'    => 'string?'
	);

}

function api_store_tax_zones_list($params) {

	$rows = api_rows("SELECT * FROM tax_zones ORDER BY name ASC, id ASC");

	$areas = api_store_zone_areas('tax_zones', array_map(function ($row) { return $row['id']; }, $rows));

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_store_tax_zone_present($row, $areas);

	}

	api_ok_list($out, max(1, count($out)), null);

}

/* ----- Countries ----------------------------------------------------------- */

function api_store_country_present($row, $states = array()) {

	return array(
		'id'                => (int)$row['id'],
		'name'              => (string)$row['name'],
		'code'              => (string)$row['code'],
		// Selected first on the address forms.
		'default'           => ((int)$row['default_selected'] === 1),
		'zip_code_required' => ((int)$row['zip_code_required'] === 1),
		'transit_adjustment_days' => (int)$row['transit_adjustment_days'],
		'states'            => $states
	);

}

function api_store_country_schema() {

	return array(
		'id'                => 'integer',
		'name'              => 'string',
		'code'              => 'string',
		'default'           => 'boolean',
		'zip_code_required' => 'boolean',
		'transit_adjustment_days' => 'integer',
		'states'            => array(array('id' => 'integer', 'code' => 'string', 'name' => 'string'))
	);

}

function api_store_countries_list($params) {

	$where = '';

	if (isset($params['code']) && $params['code'] !== '') {

		$where = " WHERE code = '" . escape($params['code']) . "'";

	}

	$rows = api_rows("SELECT * FROM countries" . $where . " ORDER BY name ASC, id ASC");

	$states = array();

	if (!empty($rows)) {

		$ids = implode(',', array_map(function ($row) { return (int)$row['id']; }, $rows));

		foreach (api_rows("SELECT id, code, name, country_id FROM states WHERE country_id IN (" . $ids . ") ORDER BY name ASC, id ASC") as $state) {

			$states[(int)$state['country_id']][] = array('id' => (int)$state['id'], 'code' => (string)$state['code'], 'name' => (string)$state['name']);

		}

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_store_country_present($row, isset($states[(int)$row['id']]) ? $states[(int)$row['id']] : array());

	}

	api_ok_list($out, max(1, count($out)), null);

}

/* ----- Currencies ---------------------------------------------------------- */

function api_store_currency_present($row) {

	return array(
		'id'            => (int)$row['id'],
		'name'          => (string)$row['name'],
		'code'          => (string)$row['code'],
		// The screen keeps the symbol as HTML (&euro;); it is written out here.
		'symbol'        => html_entity_decode((string)$row['symbol'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
		// How much of this currency one unit of the base currency buys.
		'exchange_rate' => (float)$row['exchange_rate'],
		'base'          => ((int)$row['base'] === 1),
		'updated_at'    => api_time($row['last_modified_timestamp'])
	);

}

function api_store_currency_schema() {

	return array(
		'id'            => 'integer',
		'name'          => 'string',
		'code'          => 'string',
		'symbol'        => 'string',
		'exchange_rate' => 'number',
		'base'          => 'boolean',
		'updated_at'    => 'string?'
	);

}

function api_store_currencies_list($params) {

	$out = array();

	foreach (api_rows("SELECT * FROM currencies ORDER BY base DESC, name ASC, id ASC") as $row) {

		$out[] = api_store_currency_present($row);

	}

	api_ok_list($out, max(1, count($out)), null);

}
