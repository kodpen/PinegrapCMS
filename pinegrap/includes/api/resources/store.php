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

function api_store_shipping_method_present($row, $zone_ids = array(), $excluded_dates = array()) {

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
		// The carrier label service Create Label uses and whose quote a live
		// rate charges; label_service '' takes the cheapest offer.
		'label_driver'      => (isset($row['label_driver']) && ((string)$row['label_driver'] !== '')) ? (string)$row['label_driver'] : null,
		'label_service'     => isset($row['label_service']) ? (string)$row['label_service'] : '',
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
		// Days nothing is in transit (holidays), Y-m-d.
		'excluded_transit_dates' => $excluded_dates,
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
		'label_driver'      => 'string?',
		'label_service'     => 'string',
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
		'excluded_transit_dates' => 'string[]',
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

function api_store_shipping_method_excluded_dates($ids) {

	$dates = array();

	$ids = array_values(array_unique(array_map('intval', $ids)));

	if (empty($ids)) {

		return $dates;

	}

	foreach (api_rows("SELECT shipping_method_id, date FROM excluded_transit_dates
		WHERE shipping_method_id IN (" . implode(',', $ids) . ")
		ORDER BY shipping_method_id ASC, date ASC") as $row) {

		$dates[(int)$row['shipping_method_id']][] = (string)$row['date'];

	}

	return $dates;

}

function api_store_shipping_methods_list($params) {

	$where = '';

	if (isset($params['enabled'])) {

		$where = " WHERE status = '" . ($params['enabled'] ? 'enabled' : 'disabled') . "'";

	}

	$rows = api_rows("SELECT * FROM shipping_methods" . $where . " ORDER BY name ASC, id ASC");

	$ids = array_map(function ($row) { return $row['id']; }, $rows);

	$zones = api_store_shipping_method_zone_ids($ids);

	$dates = api_store_shipping_method_excluded_dates($ids);

	$out = array();

	foreach ($rows as $row) {

		$id = (int)$row['id'];

		$out[] = api_store_shipping_method_present($row, isset($zones[$id]) ? $zones[$id] : array(), isset($dates[$id]) ? $dates[$id] : array());

	}

	api_ok_list($out, max(1, count($out)), null);

}

function api_store_shipping_methods_get($params) {

	api_store_shipping_method_answer((int)$params['id']);

}

function api_store_shipping_method_load_or_fail($id) {

	$row = api_row("SELECT * FROM shipping_methods WHERE id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Shipping Method'));

	}

	return $row;

}

function api_store_shipping_method_answer($id, $status_code = 200) {

	$row = api_store_shipping_method_load_or_fail($id);

	$id = (int)$row['id'];

	$zones = api_store_shipping_method_zone_ids(array($id));

	$dates = api_store_shipping_method_excluded_dates(array($id));

	api_ok(api_store_shipping_method_present($row, isset($zones[$id]) ? $zones[$id] : array(), isset($dates[$id]) ? $dates[$id] : array()), $status_code);

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

// A currency symbol as plain text. The currency screen keeps it as HTML
// (&euro;, &#8378;) for the pages that print it; an API client draws it in a
// price label of its own, where the entity would show as written. GET /meta
// and GET /store/currencies give the same text through this one rule.
function api_currency_symbol_text($symbol) {

	return html_entity_decode((string)$symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8');

}

function api_store_currency_present($row) {

	return array(
		'id'            => (int)$row['id'],
		'name'          => (string)$row['name'],
		'code'          => (string)$row['code'],
		'symbol'        => api_currency_symbol_text($row['symbol']),
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

// Product feeds (includes/fn/product_feeds.php): per format whether it is
// on, the address a platform reads it from, and how its last build went - for
// an integration that hands the address to a platform (Merchant Center's
// scheduled fetch) or watches that the feeds keep being built. The address
// carries the feed's token; a key that may read the products may read the
// feed of them too, so products:read is the scope.
//
// state is the System Status rule (pg_product_feed_health()): off, ok, stale
// (never built, or not for two days) or failing (the last build failed and
// the file built before it is still served).
function api_store_feed_present($format, $entry, $state) {

	$formats = pg_product_feed_formats();
	$token = ($entry['token_hash'] !== '') ? pg_product_feed_token($entry) : '';

	return array(
		'format'       => (string)$format,
		'enabled'      => ((int)$entry['enabled'] === 1),
		// null before the feed has an address, or where the site's key can
		// no longer open the sealed token (a new address is made on the card).
		'url'          => ($token !== '') ? pg_product_feed_url($token) : null,
		'content_type' => (string)$formats[$format]['content_type'],
		'state'        => (string)$state,
		'built_at'     => api_time($entry['built_at']),
		'products'     => (int)$entry['count'],
		'bytes'        => (int)$entry['bytes'],
		'error'        => ($entry['error'] !== '') ? (string)$entry['error'] : null,
		'skipped'      => api_store_feed_skipped($entry['skipped'])
	);

}

// Why products were left out of the last build, reason => count as a list.
// Reasons: no_address, donation, recurring, no_price, not_in_catalog,
// excluded_group (the store's rules), no_title, no_brand, no_image (what the
// format needs).
function api_store_feed_skipped($skipped) {

	$out = array();

	foreach ((array)$skipped as $reason => $count) {

		$out[] = array('reason' => (string)$reason, 'count' => (int)$count);

	}

	return $out;

}

function api_store_feed_schema() {

	return array(
		'format'       => 'string',
		'enabled'      => 'boolean',
		'url'          => 'string?',
		'content_type' => 'string',
		'state'        => 'string',
		'built_at'     => 'string?',
		'products'     => 'integer',
		'bytes'        => 'integer',
		'error'        => 'string?',
		'skipped'      => array(array('reason' => 'string', 'count' => 'integer'))
	);

}

function api_store_feeds_list($params) {

	$settings = pg_product_feed_settings();
	$health = pg_product_feed_health($settings, time());
	$states = array();

	foreach ($health['rows'] as $row) {

		$states[$row['format']] = ($row['state'] === 'fail') ? 'failing' : (($row['state'] === 'warn') ? 'stale' : 'ok');

	}

	$out = array();

	foreach ($settings['formats'] as $format => $entry) {

		$out[] = api_store_feed_present($format, $entry, isset($states[$format]) ? $states[$format] : 'off');

	}

	api_ok_list($out, max(1, count($out)), null);

}


/* ----- Zone and tax zone writes -------------------------------------------- */

// The country ids of ISO codes, in the order sent; an unknown code is 422
// naming the field.
function api_store_zone_country_ids($codes, $field) {

	$ids = array();

	foreach ((array)$codes as $code) {

		if (!is_string($code) || !preg_match('/^[A-Za-z]{2,3}$/', trim($code))) {

			api_fail_validation(lang(array('string' => '{var:1} must be a list of country codes.', 'vars' => $field)), $field);

		}

		$id = (int)api_value("SELECT id FROM countries WHERE code = '" . escape(strtoupper(trim($code))) . "' LIMIT 1");

		if ($id <= 0) {

			api_fail_validation(lang(array('string' => '{var:1}: there is no country with the code {var:2}.', 'vars' => array($field, strtoupper(trim($code))))), $field);

		}

		$ids[] = $id;

	}

	return array_values(array_unique($ids));

}

// The state ids of {country_code, code} objects, as GET /store/zones lists
// them; an unknown state is 422 naming the field.
function api_store_zone_state_ids($states, $field) {

	$ids = array();

	foreach ((array)$states as $state) {

		$country = (is_array($state) && isset($state['country_code']) && is_string($state['country_code'])) ? strtoupper(trim($state['country_code'])) : '';

		$code = (is_array($state) && isset($state['code']) && is_scalar($state['code'])) ? trim((string)$state['code']) : '';

		if (($country === '') || ($code === '')) {

			api_fail_validation(lang(array('string' => '{var:1} must be a list of objects with country_code and code.', 'vars' => $field)), $field);

		}

		$id = (int)api_value("SELECT states.id FROM states
			INNER JOIN countries ON countries.id = states.country_id
			WHERE (countries.code = '" . escape($country) . "') AND (states.code = '" . escape($code) . "')
			LIMIT 1");

		if ($id <= 0) {

			api_fail_validation(lang(array('string' => '{var:1}: there is no state {var:2} in the country {var:3}.', 'vars' => array($field, $code, $country))), $field);

		}

		$ids[] = $id;

	}

	return array_values(array_unique($ids));

}

// The ids a zone covers now, for an update that leaves one list unsent.
function api_store_zone_link_ids_of($kind, $id) {

	$t = pg_store_zone_tables($kind);

	$out = array('countries' => array(), 'states' => array());

	foreach (api_rows("SELECT country_id FROM " . $t['countries'] . " WHERE " . $t['key'] . " = '" . (int)$id . "' ORDER BY country_id ASC") as $row) {

		$out['countries'][] = (int)$row['country_id'];

	}

	foreach (api_rows("SELECT state_id FROM " . $t['states'] . " WHERE " . $t['key'] . " = '" . (int)$id . "' ORDER BY state_id ASC") as $row) {

		$out['states'][] = (int)$row['state_id'];

	}

	return $out;

}

// A zone's name and rates after an update: the stored row with the fields
// sent laid over it ('' is no field sent; a sent name is trimmed).
function api_store_zone_save_data($row, $params) {

	$data = array('name' => (string)$row['name']);

	foreach (pg_store_zone_rate_columns() as $column) {

		$data[$column] = (int)$row[$column];

	}

	if (array_key_exists('name', $params)) {

		$data['name'] = trim((string)$params['name']);

	}

	foreach (pg_store_zone_rate_columns() as $column) {

		if (array_key_exists($column, $params)) {

			$data[$column] = (int)$params[$column];

		}

	}

	return $data;

}

function api_store_zone_load_or_fail($kind, $id) {

	$t = pg_store_zone_tables($kind);

	$row = api_row("SELECT * FROM " . $t['table'] . " WHERE id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(($kind === 'tax_zone') ? lang('Tax Zone') : lang('Zone'));

	}

	return $row;

}

function api_store_zone_answer($kind, $id, $status_code = 200) {

	$row = api_store_zone_load_or_fail($kind, $id);

	if ($kind === 'tax_zone') {

		api_ok(api_store_tax_zone_present($row, api_store_zone_areas('tax_zones', array($id))), $status_code);

	}

	api_ok(api_store_zone_present($row, api_store_zone_areas('zones', array($id))), $status_code);

}

function api_store_zones_get($params) {

	api_store_zone_answer('zone', (int)$params['id']);

}

function api_store_tax_zones_get($params) {

	api_store_zone_answer('tax_zone', (int)$params['id']);

}

// A create, or an update of the zone $row, checked and resolved before
// anything is written: the fields sent over the stored ones, the countries
// and states replaced only when their list is sent.
function api_store_zone_write_prepare($kind, $params, $row = null) {

	if ($kind === 'tax_zone') {

		$data = array('name' => ($row === null) ? '' : (string)$row['name'], 'tax_rate' => ($row === null) ? null : (float)$row['tax_rate']);

		if (array_key_exists('name', $params)) {

			$data['name'] = trim((string)$params['name']);

		}

		if (array_key_exists('rate', $params)) {

			$data['tax_rate'] = (float)$params['rate'];

		}

		if ($data['tax_rate'] === null) {

			api_fail_validation(lang('Enter a tax rate between 0 and 100.'), 'rate');

		}

	} else {

		$data = api_store_zone_save_data(($row === null) ? array('name' => '', 'base_rate' => 0, 'primary_weight_rate' => 0, 'secondary_weight_rate' => 0, 'item_rate' => 0) : $row, $params);

	}

	if ($data['name'] === '') {

		api_fail_validation(lang('Enter a name.'), 'name');

	}

	$id = ($row === null) ? 0 : (int)$row['id'];

	$current = ($row === null) ? array('countries' => array(), 'states' => array()) : api_store_zone_link_ids_of($kind, $id);

	return array(
		'id'          => $id,
		'data'        => $data,
		'country_ids' => array_key_exists('country_codes', $params) ? api_store_zone_country_ids($params['country_codes'], 'country_codes') : $current['countries'],
		'state_ids'   => array_key_exists('states', $params) ? api_store_zone_state_ids($params['states'], 'states') : $current['states']
	);

}

// Writes what api_store_zone_write_prepare() resolved; the zone's id.
function api_store_zone_write_commit($kind, $prepared) {

	$app = api_current_app();

	$data = $prepared['data'];

	$created = ($prepared['id'] <= 0);

	if ($kind === 'tax_zone') {

		$saved = pg_store_tax_zone_save($prepared['id'], $data['name'], $data['tax_rate'], $prepared['country_ids'], $prepared['state_ids'], (int)$app['owner']['id']);

		$text = $created ? 'Tax zone ({var:1}) was created through the API by the application {var:2} (key {var:3}).' : 'Tax zone ({var:1}) was changed through the API by the application {var:2} (key {var:3}).';

	} else {

		$saved = pg_store_zone_save($prepared['id'], $data, $prepared['country_ids'], $prepared['state_ids'], (int)$app['owner']['id']);

		$text = $created ? 'Shipping zone ({var:1}) was created through the API by the application {var:2} (key {var:3}).' : 'Shipping zone ({var:1}) was changed through the API by the application {var:2} (key {var:3}).';

	}

	if ($saved <= 0) {

		api_fail_server();

	}

	log_activity(lang(array('string' => $text, 'vars' => array($data['name'], $app['name'], $app['api_key']))), $app['owner']['username']);

	return $saved;

}

function api_store_zone_remove_commit($kind, $row) {

	$app = api_current_app();

	$id = (int)$row['id'];

	$done = ($kind === 'tax_zone') ? pg_store_tax_zone_delete($id, (string)$row['name']) : pg_store_zone_delete($id, (string)$row['name']);

	if (!$done) {

		api_fail_server();

	}

	$text = ($kind === 'tax_zone')
		? 'Tax zone ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).'
		: 'Shipping zone ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).';

	log_activity(lang(array('string' => $text, 'vars' => array((string)$row['name'], $app['name'], $app['api_key']))), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true));

}

function api_store_zones_create($params) {

	$prepared = api_store_zone_write_prepare('zone', $params);

	api_dry_run_stop('created', 'zone', array('name' => $prepared['data']['name']));

	api_store_zone_answer('zone', api_store_zone_write_commit('zone', $prepared), 201);

}

function api_store_zones_update($params) {

	$prepared = api_store_zone_write_prepare('zone', $params, api_store_zone_load_or_fail('zone', $params['id']));

	api_dry_run_stop('updated', 'zone', array('id' => $prepared['id'], 'name' => $prepared['data']['name']));

	api_store_zone_answer('zone', api_store_zone_write_commit('zone', $prepared));

}

function api_store_zones_delete($params) {

	$row = api_store_zone_load_or_fail('zone', $params['id']);

	api_dry_run_stop('deleted', 'zone', array('id' => (int)$row['id'], 'name' => (string)$row['name']));

	api_store_zone_remove_commit('zone', $row);

}

function api_store_tax_zones_create($params) {

	$prepared = api_store_zone_write_prepare('tax_zone', $params);

	api_dry_run_stop('created', 'tax_zone', array('name' => $prepared['data']['name']));

	api_store_zone_answer('tax_zone', api_store_zone_write_commit('tax_zone', $prepared), 201);

}

function api_store_tax_zones_update($params) {

	$prepared = api_store_zone_write_prepare('tax_zone', $params, api_store_zone_load_or_fail('tax_zone', $params['id']));

	api_dry_run_stop('updated', 'tax_zone', array('id' => $prepared['id'], 'name' => $prepared['data']['name']));

	api_store_zone_answer('tax_zone', api_store_zone_write_commit('tax_zone', $prepared));

}

function api_store_tax_zones_delete($params) {

	$row = api_store_zone_load_or_fail('tax_zone', $params['id']);

	api_dry_run_stop('deleted', 'tax_zone', array('id' => (int)$row['id'], 'name' => (string)$row['name']));

	api_store_zone_remove_commit('tax_zone', $row);

}

/* ----- Shipping method writes ---------------------------------------------- */

// The stored row a new shipping method starts from: enabled, street
// addresses and PO boxes allowed, handled and in transit on weekdays,
// offered every day all day, with no time window.
function api_store_shipping_method_new_row() {

	$row = array(
		'name' => '', 'description' => '', 'code' => '', 'carrier_title' => '', 'carrier_vkn' => '',
		'status' => 'enabled', 'start_time' => '1000-01-01 00:00:00', 'end_time' => '9999-12-31 23:59:59',
		'service' => '', 'realtime_rate' => 0, 'label_driver' => '', 'label_service' => '', 'base_rate' => 0, 'variable_base_rate' => 0,
		'primary_weight_rate' => 0, 'primary_weight_rate_first_item_excluded' => 0,
		'secondary_weight_rate' => 0, 'secondary_weight_rate_first_item_excluded' => 0,
		'item_rate' => 0, 'item_rate_first_item_excluded' => 0,
		'base_transit_days' => 0, 'adjust_transit' => 0, 'street_address' => 1, 'po_box' => 1, 'handle_days' => 0,
		'end_of_day' => '00:00:00', 'transit_on_sunday' => 0, 'transit_on_saturday' => 0, 'protected' => 0
	);

	foreach (array(2, 3, 4) as $tier) {

		$row['base_rate_' . $tier] = 0;
		$row['base_rate_' . $tier . '_subtotal'] = 0;

	}

	foreach (api_store_days() as $short => $long) {

		$weekday = !in_array($short, array('sat', 'sun'), true);

		$row['handle_' . $short] = $weekday ? 1 : 0;
		$row['ship_' . $short] = $weekday ? 1 : 0;
		$row['available_on_' . $long] = 1;
		$row['available_on_' . $long . '_cutoff_time'] = '00:00:00';

	}

	return $row;

}

// "HH:MM" as a TIME column, '' for null (all day), or null when it is not a
// time of day.
function api_store_time_of_day($value) {

	if (($value === null) || ($value === '')) {

		return '00:00:00';

	}

	if (!is_string($value) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value)) {

		return null;

	}

	return $value . ':00';

}

// Weekday short names (mon … sun) as a set, or null when one is not.
function api_store_day_set($days) {

	$set = array();

	foreach ((array)$days as $day) {

		if (!is_string($day) || !isset(api_store_days()[$day])) {

			return null;

		}

		$set[$day] = true;

	}

	return $set;

}

// The columns a shipping method is saved with: the stored row with the API
// fields sent laid over it. array($columns, null), or array(null,
// array($field, $message)) for the first field that cannot be saved.
// $params holds only the fields sent; times arrive as unix seconds.
function api_store_shipping_method_columns($row, $params) {

	$fail = function ($field, $message) { return array(null, array($field, $message)); };

	$columns = array();

	foreach (array('name', 'description', 'code', 'carrier_title', 'carrier_vkn', 'status', 'start_time', 'end_time', 'service', 'realtime_rate', 'base_rate', 'variable_base_rate', 'primary_weight_rate', 'primary_weight_rate_first_item_excluded', 'secondary_weight_rate', 'secondary_weight_rate_first_item_excluded', 'item_rate', 'item_rate_first_item_excluded', 'base_transit_days', 'adjust_transit', 'street_address', 'po_box', 'handle_days', 'end_of_day', 'transit_on_sunday', 'transit_on_saturday', 'protected', 'label_driver', 'label_service') as $column) {

		$columns[$column] = isset($row[$column]) ? (string)$row[$column] : '';

	}

	foreach (array(2, 3, 4) as $tier) {

		$columns['base_rate_' . $tier] = (string)$row['base_rate_' . $tier];
		$columns['base_rate_' . $tier . '_subtotal'] = (string)$row['base_rate_' . $tier . '_subtotal'];

	}

	foreach (api_store_days() as $short => $long) {

		foreach (array('handle_' . $short, 'ship_' . $short, 'available_on_' . $long, 'available_on_' . $long . '_cutoff_time') as $column) {

			$columns[$column] = (string)$row[$column];

		}

	}

	$text = array('name' => 'name', 'description' => 'description', 'code' => 'code', 'carrier_title' => 'carrier_title');

	foreach ($text as $field => $column) {

		if (array_key_exists($field, $params)) {

			$columns[$column] = trim((string)$params[$field]);

		}

	}

	if ($columns['name'] === '') {

		return $fail('name', lang('Enter a name.'));

	}

	if (array_key_exists('carrier_tax_number', $params)) {

		$number = trim((string)$params['carrier_tax_number']);

		$columns['carrier_vkn'] = ($number === '') ? '' : (string)pg_carrier_number_clean($number);

	}

	$flags = array('enabled' => 'status', 'protected' => 'protected', 'street_address' => 'street_address', 'po_box' => 'po_box', 'transit_on_saturday' => 'transit_on_saturday', 'transit_on_sunday' => 'transit_on_sunday', 'adjust_transit_for_country' => 'adjust_transit', 'realtime_rate' => 'realtime_rate');

	foreach ($flags as $field => $column) {

		if (array_key_exists($field, $params)) {

			$on = !empty($params[$field]);

			$columns[$column] = ($column === 'status') ? ($on ? 'enabled' : 'disabled') : ($on ? '1' : '0');

		}

	}

	foreach (array('available_from' => array('start_time', '1000-01-01 00:00:00'), 'available_until' => array('end_time', '9999-12-31 23:59:59')) as $field => $target) {

		if (array_key_exists($field, $params)) {

			$columns[$target[0]] = (($params[$field] === '') || ($params[$field] === null)) ? $target[1] : date('Y-m-d H:i:s', (int)$params[$field]);

		}

	}

	if (strcmp($columns['start_time'], $columns['end_time']) > 0) {

		return $fail('available_until', lang('available_until must not be before available_from.'));

	}

	foreach (array('primary_weight_rate', 'secondary_weight_rate', 'item_rate') as $field) {

		if (array_key_exists($field, $params)) {

			$columns[$field] = (string)(int)$params[$field];

		}

	}

	if (array_key_exists('first_item_excluded', $params)) {

		foreach (array('primary_weight' => 'primary_weight_rate_first_item_excluded', 'secondary_weight' => 'secondary_weight_rate_first_item_excluded', 'item' => 'item_rate_first_item_excluded') as $key => $column) {

			if (array_key_exists($key, (array)$params['first_item_excluded'])) {

				$columns[$column] = !empty($params['first_item_excluded'][$key]) ? '1' : '0';

			}

		}

	}

	if (array_key_exists('base_rates', $params)) {

		$tiers = array();

		foreach ((array)$params['base_rates'] as $tier) {

			if (!is_array($tier) || !isset($tier['from_subtotal'], $tier['rate']) || !is_int($tier['from_subtotal']) || !is_int($tier['rate']) || ($tier['from_subtotal'] < 0) || ($tier['rate'] < 0)) {

				return $fail('base_rates', lang('base_rates must be a list of objects with from_subtotal and rate, whole minor units of 0 or more.'));

			}

			$tiers[$tier['from_subtotal']] = $tier['rate'];

		}

		ksort($tiers);

		if (!isset($tiers[0]) || (count($tiers) > 4)) {

			return $fail('base_rates', lang('base_rates must start with a tier from_subtotal 0 and hold at most 4 tiers.'));

		}

		$columns['base_rate'] = (string)$tiers[0];

		unset($tiers[0]);

		$columns['variable_base_rate'] = empty($tiers) ? '0' : '1';

		$tier = 2;

		foreach (array(2, 3, 4) as $number) {

			$columns['base_rate_' . $number] = '0';
			$columns['base_rate_' . $number . '_subtotal'] = '0';

		}

		foreach ($tiers as $subtotal => $rate) {

			$columns['base_rate_' . $tier] = (string)$rate;
			$columns['base_rate_' . $tier . '_subtotal'] = (string)$subtotal;

			$tier++;

		}

	}

	if (array_key_exists('realtime_service', $params)) {

		$columns['service'] = (string)$params['realtime_service'];

	}

	if (array_key_exists('label_driver', $params)) {

		$driver = trim((string)$params['label_driver']);

		if (($driver !== '') && (pg_label_driver($driver) === null)) {

			return $fail('label_driver', lang('No carrier label service has that key.'));

		}

		$columns['label_driver'] = $driver;

	}

	if (array_key_exists('label_service', $params)) {

		$columns['label_service'] = trim((string)$params['label_service']);

	}

	if ($columns['label_driver'] === '') {

		$columns['label_service'] = '';

	}

	// As on the screen: real-time rates need a service that has them.
	if (!pg_shipping_method_has_realtime_rate($columns['service'], $columns['label_driver'])) {

		$columns['realtime_rate'] = '0';

	}

	foreach (array('handling_days' => 'handle_days', 'transit_days' => 'base_transit_days') as $field => $column) {

		if (array_key_exists($field, $params)) {

			$columns[$column] = (string)(int)$params[$field];

		}

	}

	foreach (array('handling_on' => 'handle_', 'transit_on' => 'ship_') as $field => $prefix) {

		if (array_key_exists($field, $params)) {

			$set = api_store_day_set($params[$field]);

			if ($set === null) {

				return $fail($field, lang(array('string' => '{var:1} must be a list of weekdays: mon, tue, wed, thu, fri, sat, sun.', 'vars' => $field)));

			}

			foreach (array_keys(api_store_days()) as $short) {

				$columns[$prefix . $short] = isset($set[$short]) ? '1' : '0';

			}

		}

	}

	if (array_key_exists('end_of_day', $params)) {

		$time = api_store_time_of_day($params['end_of_day']);

		if ($time === null) {

			return $fail('end_of_day', lang(array('string' => '{var:1} must be a time of day as HH:MM.', 'vars' => 'end_of_day')));

		}

		$columns['end_of_day'] = $time;

	}

	if (array_key_exists('offered_on', $params)) {

		$offered = array();

		foreach ((array)$params['offered_on'] as $entry) {

			$day = (is_array($entry) && isset($entry['day']) && is_string($entry['day'])) ? $entry['day'] : '';

			$until = api_store_time_of_day(is_array($entry) && array_key_exists('until', $entry) ? $entry['until'] : null);

			if (!isset(api_store_days()[$day]) || ($until === null)) {

				return $fail('offered_on', lang('offered_on must be a list of objects with day (mon … sun) and until (HH:MM or null).'));

			}

			$offered[$day] = $until;

		}

		foreach (api_store_days() as $short => $long) {

			$columns['available_on_' . $long] = isset($offered[$short]) ? '1' : '0';
			$columns['available_on_' . $long . '_cutoff_time'] = isset($offered[$short]) ? $offered[$short] : '00:00:00';

		}

	}

	return array($columns, null);

}

// Zone ids that all name a zone, or 422.
function api_store_shipping_method_zone_ids_in($ids) {

	$clean = array();

	foreach ((array)$ids as $id) {

		if (!is_int($id) || ($id < 1)) {

			api_fail_validation(lang(array('string' => '{var:1} must be a list of zone ids.', 'vars' => 'zone_ids')), 'zone_ids');

		}

		$clean[$id] = $id;

	}

	if (!empty($clean) && ((int)api_value("SELECT COUNT(*) FROM zones WHERE id IN (" . implode(',', $clean) . ")") !== count($clean))) {

		api_fail_validation(lang('zone_ids names a zone that does not exist (GET /store/zones).'), 'zone_ids');

	}

	return array_values($clean);

}

// Y-m-d dates, distinct and sorted, or 422.
function api_store_shipping_method_dates_in($dates) {

	$clean = array();

	foreach ((array)$dates as $date) {

		$parts = is_string($date) ? explode('-', $date) : array();

		if ((count($parts) !== 3) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {

			api_fail_validation(lang(array('string' => '{var:1} must be a list of dates as YYYY-MM-DD.', 'vars' => 'excluded_transit_dates')), 'excluded_transit_dates');

		}

		$clean[$date] = $date;

	}

	ksort($clean);

	return array_values($clean);

}

// A create, or an update of $row, checked before anything is written.
function api_store_shipping_method_write_prepare($params, $row = null) {

	list($columns, $error) = api_store_shipping_method_columns(($row === null) ? api_store_shipping_method_new_row() : $row, $params);

	if ($error !== null) {

		api_fail_validation($error[1], $error[0]);

	}

	$id = ($row === null) ? 0 : (int)$row['id'];

	$zones = ($row === null) ? array() : api_store_shipping_method_zone_ids(array($id));

	$dates = ($row === null) ? array() : api_store_shipping_method_excluded_dates(array($id));

	return array(
		'id'       => $id,
		'columns'  => $columns,
		'zone_ids' => array_key_exists('zone_ids', $params) ? api_store_shipping_method_zone_ids_in($params['zone_ids']) : (isset($zones[$id]) ? $zones[$id] : array()),
		'dates'    => array_key_exists('excluded_transit_dates', $params) ? api_store_shipping_method_dates_in($params['excluded_transit_dates']) : (isset($dates[$id]) ? $dates[$id] : array())
	);

}

function api_store_shipping_method_write_commit($prepared) {

	$app = api_current_app();

	$saved = pg_shipping_method_save($prepared['id'], $prepared['columns'], $prepared['zone_ids'], $prepared['dates'], (int)$app['owner']['id']);

	if ($saved <= 0) {

		api_fail_server();

	}

	$text = ($prepared['id'] <= 0)
		? 'Shipping method ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'Shipping method ({var:1}) was changed through the API by the application {var:2} (key {var:3}).';

	log_activity(lang(array('string' => $text, 'vars' => array($prepared['columns']['name'], $app['name'], $app['api_key']))), $app['owner']['username']);

	return $saved;

}

function api_store_shipping_methods_create($params) {

	$prepared = api_store_shipping_method_write_prepare($params);

	api_dry_run_stop('created', 'shipping_method', array('name' => $prepared['columns']['name']));

	api_store_shipping_method_answer(api_store_shipping_method_write_commit($prepared), 201);

}

function api_store_shipping_methods_update($params) {

	$prepared = api_store_shipping_method_write_prepare($params, api_store_shipping_method_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'shipping_method', array('id' => $prepared['id'], 'name' => $prepared['columns']['name']));

	api_store_shipping_method_answer(api_store_shipping_method_write_commit($prepared));

}

function api_store_shipping_methods_delete($params) {

	$app = api_current_app();

	$row = api_store_shipping_method_load_or_fail($params['id']);

	$id = (int)$row['id'];

	api_dry_run_stop('deleted', 'shipping_method', array('id' => $id, 'name' => (string)$row['name']));

	if (!pg_shipping_method_delete($id, (string)$row['name'])) {

		api_fail_server();

	}

	log_activity(lang(array(
		'string' => 'Shipping method ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array((string)$row['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true));

}
