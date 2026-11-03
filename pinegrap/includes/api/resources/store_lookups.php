<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The store's lookup lists over the external API: ship date adjustments,
// verified shipping addresses and referral sources. Reads
// under orders:read, writes under store:write, with the checks of the
// screens' forms (includes/forms/) and the write path the screens call
// (includes/fn/store_lookups.php). No events.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/ship_date_adjustment_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/verified_shipping_address_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/referral_source_form.php');

/* ---------------------------------------------------------------------------
   Ship date adjustments
   --------------------------------------------------------------------------- */

function api_store_ship_date_adjustment_present($row) {

	$fields = pg_ship_date_adjustment_form_fields((int)$row['adjustment']);

	return array(
		'id'                 => (int)$row['id'],
		'zip_code_prefix'    => (string)$row['zip_code_prefix'],
		'shipping_method_id' => (int)$row['shipping_method_id'],
		'days'               => (int)$fields['adjustment_days'],
		'direction'          => (string)$fields['adjustment_type'],
		'adjustment'         => (int)$row['adjustment']
	);

}

function api_store_ship_date_adjustment_schema() {

	return array(
		'id'                 => 'integer',
		'zip_code_prefix'    => 'string',
		'shipping_method_id' => 'integer',
		'days'               => 'integer',
		'direction'          => 'string',
		'adjustment'         => 'integer'
	);

}

// An adjustment after a create or an update: the stored row with the fields
// sent laid over it, as the screen's fields (days and direction). Pure.
function api_store_ship_date_adjustment_values($row, $params) {

	$fields = ($row === null)
		? array('adjustment_days' => '', 'adjustment_type' => 'later')
		: pg_ship_date_adjustment_form_fields((int)$row['adjustment']);

	$values = array(
		'zip_code_prefix'    => ($row === null) ? '' : (string)$row['zip_code_prefix'],
		'shipping_method_id' => ($row === null) ? 0 : (int)$row['shipping_method_id'],
		'days'               => (string)$fields['adjustment_days'],
		'direction'          => (string)$fields['adjustment_type']
	);

	if (array_key_exists('zip_code_prefix', $params)) {

		$values['zip_code_prefix'] = trim((string)$params['zip_code_prefix']);

	}

	if (array_key_exists('shipping_method_id', $params)) {

		$values['shipping_method_id'] = (int)$params['shipping_method_id'];

	}

	if (array_key_exists('days', $params)) {

		$values['days'] = trim((string)$params['days']);

	}

	if (array_key_exists('direction', $params)) {

		$values['direction'] = (string)$params['direction'];

	}

	return $values;

}

function api_store_ship_date_adjustment_load_or_fail($id) {

	$row = api_row("SELECT * FROM ship_date_adjustments WHERE id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Ship Date Adjustment'));

	}

	return $row;

}

function api_store_ship_date_adjustment_answer($id, $status_code = 200) {

	api_ok(api_store_ship_date_adjustment_present(api_store_ship_date_adjustment_load_or_fail($id)), $status_code);

}

// GET /store/ship-date-adjustments
function api_store_ship_date_adjustments_list($params) {

	$out = array();

	foreach (api_rows("SELECT * FROM ship_date_adjustments ORDER BY zip_code_prefix ASC, shipping_method_id ASC, id ASC") as $row) {

		$out[] = api_store_ship_date_adjustment_present($row);

	}

	api_ok_list($out, max(1, count($out)), null);

}

// GET /store/ship-date-adjustments/{id}
function api_store_ship_date_adjustments_get($params) {

	api_store_ship_date_adjustment_answer((int)$params['id']);

}

// The screen's checks (pg_ship_date_adjustment_form_check()) in its order:
// required fields, three characters, a prefix and method no other
// adjustment has (409 adjustment_taken), a method that exists, the
// direction, the days.
function api_store_ship_date_adjustment_prepare($params, $row) {

	$values = api_store_ship_date_adjustment_values($row, $params);

	$id = ($row === null) ? 0 : (int)$row['id'];

	$required = array(
		'zip_code_prefix'    => array($values['zip_code_prefix'] === '', lang('Zip Code Prefix is required.')),
		'shipping_method_id' => array($values['shipping_method_id'] <= 0, lang('Shipping Method is required.')),
		'days'               => array($values['days'] === '', lang('The number of days is required.')),
		'direction'          => array($values['direction'] === '', lang('"earlier" or "later" is required.'))
	);

	foreach ($required as $field => $check) {

		if ($check[0]) {

			api_fail_validation($check[1], $field);

		}

	}

	if (mb_strlen($values['zip_code_prefix']) != 3) {

		api_fail_validation(lang('Sorry, the zip code prefix must contain 3 characters.'), 'zip_code_prefix');

	}

	if (pg_ship_date_adjustment_form_taken($values['zip_code_prefix'], $values['shipping_method_id'], ($row === null) ? null : $id)) {

		api_fail(409, 'adjustment_taken', lang('Sorry, there is already a ship date adjustment for the zip code prefix and shipping method you selected.'), 'zip_code_prefix');

	}

	if (!pg_ship_date_adjustment_form_method_exists($values['shipping_method_id'])) {

		api_fail_validation(lang('Shipping Method is required.'), 'shipping_method_id');

	}

	if (!pg_ship_date_adjustment_form_type_valid($values['direction'])) {

		api_fail_validation(lang('"earlier" or "later" is required.'), 'direction');

	}

	if (!pg_ship_date_adjustment_form_days_valid($values['days'])) {

		api_fail_validation(lang('Please enter a valid number of days.'), 'days');

	}

	return array(
		'id'         => $id,
		'values'     => $values,
		'adjustment' => (int)pg_ship_date_adjustment_form_adjustment((int)$values['days'], $values['direction'])
	);

}

function api_store_ship_date_adjustment_commit($prepared) {

	$app = api_current_app();

	$saved = pg_store_ship_date_adjustment_save($prepared['id'], $prepared['values']['zip_code_prefix'], $prepared['values']['shipping_method_id'], $prepared['adjustment'], (int)$app['owner']['id']);

	if ($saved <= 0) {

		api_fail_server();

	}

	api_store_region_log(($prepared['id'] <= 0)
		? 'Ship date adjustment ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'Ship date adjustment ({var:1}) was changed through the API by the application {var:2} (key {var:3}).', $prepared['values']['zip_code_prefix']);

	return $saved;

}

// POST /store/ship-date-adjustments
function api_store_ship_date_adjustments_create($params) {

	$prepared = api_store_ship_date_adjustment_prepare($params, null);

	api_dry_run_stop('created', 'ship_date_adjustment', array('zip_code_prefix' => $prepared['values']['zip_code_prefix'], 'shipping_method_id' => $prepared['values']['shipping_method_id'], 'adjustment' => $prepared['adjustment']));

	api_store_ship_date_adjustment_answer(api_store_ship_date_adjustment_commit($prepared), 201);

}

// POST /store/ship-date-adjustments/{id}
function api_store_ship_date_adjustments_update($params) {

	$prepared = api_store_ship_date_adjustment_prepare($params, api_store_ship_date_adjustment_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'ship_date_adjustment', array('id' => $prepared['id'], 'zip_code_prefix' => $prepared['values']['zip_code_prefix'], 'shipping_method_id' => $prepared['values']['shipping_method_id'], 'adjustment' => $prepared['adjustment']));

	api_store_ship_date_adjustment_answer(api_store_ship_date_adjustment_commit($prepared));

}

// DELETE /store/ship-date-adjustments/{id} and its POST twin
function api_store_ship_date_adjustments_delete($params) {

	$row = api_store_ship_date_adjustment_load_or_fail($params['id']);

	api_dry_run_stop('deleted', 'ship_date_adjustment', array('id' => (int)$row['id'], 'zip_code_prefix' => (string)$row['zip_code_prefix']));

	if (!pg_store_ship_date_adjustment_delete((int)$row['id'])) {

		api_fail_server();

	}

	api_store_region_log('Ship date adjustment ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).', (string)$row['zip_code_prefix']);

	api_ok(array('id' => (int)$row['id'], 'deleted' => true));

}

/* ---------------------------------------------------------------------------
   Verified shipping addresses
   --------------------------------------------------------------------------- */

function api_store_verified_address_present($row) {

	return array(
		'id'           => (int)$row['id'],
		'company'      => (string)$row['company'],
		'address_1'    => (string)$row['address_1'],
		'address_2'    => (string)$row['address_2'],
		'city'         => (string)$row['city'],
		'state_id'     => (int)$row['state_id'],
		'state_code'   => (isset($row['state_code']) && ((string)$row['state_code'] !== '')) ? (string)$row['state_code'] : null,
		'country_code' => (isset($row['country_code']) && ((string)$row['country_code'] !== '')) ? (string)$row['country_code'] : null,
		'zip_code'     => (string)$row['zip_code']
	);

}

function api_store_verified_address_schema() {

	return array(
		'id'           => 'integer',
		'company'      => 'string',
		'address_1'    => 'string',
		'address_2'    => 'string',
		'city'         => 'string',
		'state_id'     => 'integer',
		'state_code'   => 'string?',
		'country_code' => 'string?',
		'zip_code'     => 'string'
	);

}

// An address after a create or an update, in the shape of
// pg_verified_shipping_address_form_values(). Pure.
function api_store_verified_address_values($row, $params) {

	$values = array();

	foreach (array('company', 'address_1', 'address_2', 'city', 'state_id', 'zip_code') as $column) {

		$values[$column] = ($row === null) ? '' : (string)$row[$column];

		if (array_key_exists($column, $params)) {

			$values[$column] = trim((string)$params[$column]);

		}

	}

	if ($values['state_id'] === '0') {

		$values['state_id'] = '';

	}

	return $values;

}

function api_store_verified_address_select() {

	return "SELECT verified_shipping_addresses.*, states.code AS state_code, countries.code AS country_code
		FROM verified_shipping_addresses
		LEFT JOIN states ON states.id = verified_shipping_addresses.state_id
		LEFT JOIN countries ON countries.id = states.country_id";

}

function api_store_verified_address_load_or_fail($id) {

	$row = api_row(api_store_verified_address_select() . " WHERE verified_shipping_addresses.id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Verified Shipping Address'));

	}

	return $row;

}

function api_store_verified_address_answer($id, $status_code = 200) {

	api_ok(api_store_verified_address_present(api_store_verified_address_load_or_fail($id)), $status_code);

}

// GET /store/verified-addresses: newest first, cursor paged.
function api_store_verified_addresses_list($params) {

	$where = array('1');

	if (isset($params['state_id']) && ((int)$params['state_id'] > 0)) {

		$where[] = "verified_shipping_addresses.state_id = '" . (int)$params['state_id'] . "'";

	}

	if (isset($params['cursor']) && ($params['cursor'] !== '')) {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "verified_shipping_addresses.id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_store_verified_address_select() . "
		WHERE " . implode(' AND ', $where) . "
		ORDER BY verified_shipping_addresses.id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_store_verified_address_present($row);

	}

	api_ok_list($out, $limit, $next);

}

// GET /store/verified-addresses/{id}
function api_store_verified_addresses_get($params) {

	api_store_verified_address_answer((int)$params['id']);

}

// The screen's required fields (pg_verified_shipping_address_form_validate())
// and a state that exists, which the screen's pick list guarantees.
function api_store_verified_address_prepare($params, $row) {

	$values = api_store_verified_address_values($row, $params);

	$required = array(
		'address_1' => lang('Address 1 is required.'),
		'city'      => lang('City is required.'),
		'state_id'  => lang('State is required.'),
		'zip_code'  => lang('Zip Code is required.')
	);

	foreach ($required as $field => $message) {

		if ($values[$field] === '') {

			api_fail_validation($message, $field);

		}

	}

	if ((int)api_value("SELECT COUNT(*) FROM states WHERE id = '" . (int)$values['state_id'] . "'") < 1) {

		api_fail_validation(lang('State is required.'), 'state_id');

	}

	return array('id' => ($row === null) ? 0 : (int)$row['id'], 'values' => $values);

}

function api_store_verified_address_commit($prepared) {

	$app = api_current_app();

	$saved = pg_store_verified_address_save($prepared['id'], $prepared['values'], (int)$app['owner']['id']);

	if ($saved <= 0) {

		api_fail_server();

	}

	api_store_region_log(($prepared['id'] <= 0)
		? 'Verified shipping address ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'Verified shipping address ({var:1}) was changed through the API by the application {var:2} (key {var:3}).', pg_verified_shipping_address_form_name($prepared['values']['company'], $prepared['values']['address_1']));

	return $saved;

}

// POST /store/verified-addresses
function api_store_verified_addresses_create($params) {

	$prepared = api_store_verified_address_prepare($params, null);

	api_dry_run_stop('created', 'verified_address', array('address_1' => $prepared['values']['address_1'], 'state_id' => (int)$prepared['values']['state_id']));

	api_store_verified_address_answer(api_store_verified_address_commit($prepared), 201);

}

// POST /store/verified-addresses/{id}
function api_store_verified_addresses_update($params) {

	$prepared = api_store_verified_address_prepare($params, api_store_verified_address_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'verified_address', array('id' => $prepared['id'], 'address_1' => $prepared['values']['address_1'], 'state_id' => (int)$prepared['values']['state_id']));

	api_store_verified_address_answer(api_store_verified_address_commit($prepared));

}

// DELETE /store/verified-addresses/{id} and its POST twin
function api_store_verified_addresses_delete($params) {

	$row = api_store_verified_address_load_or_fail($params['id']);

	api_dry_run_stop('deleted', 'verified_address', array('id' => (int)$row['id'], 'address_1' => (string)$row['address_1']));

	if (!pg_store_verified_address_delete((int)$row['id'])) {

		api_fail_server();

	}

	api_store_region_log('Verified shipping address ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).', pg_verified_shipping_address_form_name((string)$row['company'], (string)$row['address_1']));

	api_ok(array('id' => (int)$row['id'], 'deleted' => true));

}

/* ---------------------------------------------------------------------------
   Referral sources
   --------------------------------------------------------------------------- */

function api_store_referral_source_present($row) {

	return array(
		'id'         => (int)$row['id'],
		'name'       => (string)$row['name'],
		'code'       => (string)$row['code'],
		'sort_order' => (int)$row['sort_order']
	);

}

function api_store_referral_source_schema() {

	return array(
		'id'         => 'integer',
		'name'       => 'string',
		'code'       => 'string',
		'sort_order' => 'integer'
	);

}

// A source after a create or an update, in the shape of
// pg_referral_source_form_values(). Pure.
function api_store_referral_source_values($row, $params) {

	$values = array(
		'name'       => ($row === null) ? '' : trim((string)$row['name']),
		'code'       => ($row === null) ? '' : trim((string)$row['code']),
		'sort_order' => ($row === null) ? '' : (string)(int)$row['sort_order']
	);

	foreach (array('name', 'code') as $field) {

		if (array_key_exists($field, $params)) {

			$values[$field] = trim((string)$params[$field]);

		}

	}

	if (array_key_exists('sort_order', $params)) {

		$values['sort_order'] = (string)(int)$params['sort_order'];

	}

	return $values;

}

function api_store_referral_source_load_or_fail($id) {

	$row = api_row("SELECT * FROM referral_sources WHERE id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Referral Source'));

	}

	return $row;

}

function api_store_referral_source_answer($id, $status_code = 200) {

	api_ok(api_store_referral_source_present(api_store_referral_source_load_or_fail($id)), $status_code);

}

// GET /store/referral-sources: in the order the contact screen offers them.
function api_store_referral_sources_list($params) {

	$out = array();

	foreach (api_rows("SELECT * FROM referral_sources ORDER BY sort_order ASC, name ASC, id ASC") as $row) {

		$out[] = api_store_referral_source_present($row);

	}

	api_ok_list($out, max(1, count($out)), null);

}

// GET /store/referral-sources/{id}
function api_store_referral_sources_get($params) {

	api_store_referral_source_answer((int)$params['id']);

}

// The screen's check (pg_referral_source_form_error()): a blank name or code
// is 422; a name or code another source has is 409 referral_source_taken.
function api_store_referral_source_prepare($params, $row) {

	$values = api_store_referral_source_values($row, $params);

	$id = ($row === null) ? 0 : (int)$row['id'];

	$error = pg_referral_source_form_error($values, array());

	if ($error !== '') {

		api_fail_validation($error, ($values['name'] === '') ? 'name' : 'code');

	}

	$others = pg_store_referral_source_others(($row === null) ? null : $id);

	if ($others === false) {

		api_fail_server();

	}

	$error = pg_referral_source_form_error($values, $others);

	if ($error !== '') {

		api_fail(409, 'referral_source_taken', $error, 'code');

	}

	return array('id' => $id, 'values' => $values);

}

function api_store_referral_source_commit($prepared) {

	$app = api_current_app();

	$saved = pg_store_referral_source_save($prepared['id'], $prepared['values'], (int)$app['owner']['id']);

	if ($saved <= 0) {

		api_fail_server();

	}

	api_store_region_log(($prepared['id'] <= 0)
		? 'Referral source ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'Referral source ({var:1}) was changed through the API by the application {var:2} (key {var:3}).', $prepared['values']['name']);

	return $saved;

}

// POST /store/referral-sources
function api_store_referral_sources_create($params) {

	$prepared = api_store_referral_source_prepare($params, null);

	api_dry_run_stop('created', 'referral_source', array('name' => $prepared['values']['name'], 'code' => $prepared['values']['code']));

	api_store_referral_source_answer(api_store_referral_source_commit($prepared), 201);

}

// POST /store/referral-sources/{id}
function api_store_referral_sources_update($params) {

	$prepared = api_store_referral_source_prepare($params, api_store_referral_source_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'referral_source', array('id' => $prepared['id'], 'name' => $prepared['values']['name'], 'code' => $prepared['values']['code']));

	api_store_referral_source_answer(api_store_referral_source_commit($prepared));

}

// DELETE /store/referral-sources/{id} and its POST twin
function api_store_referral_sources_delete($params) {

	$row = api_store_referral_source_load_or_fail($params['id']);

	api_dry_run_stop('deleted', 'referral_source', array('id' => (int)$row['id'], 'name' => (string)$row['name']));

	if (!pg_store_referral_source_delete((int)$row['id'])) {

		api_fail_server();

	}

	api_store_region_log('Referral source ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).', (string)$row['name']);

	api_ok(array('id' => (int)$row['id'], 'deleted' => true));

}
