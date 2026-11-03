<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Country and state writes (store:write): the checks of the country and state
// screens (includes/forms/country_form.php, state_form.php) and their write
// path (includes/fn/store_regions.php), so a country or state written here is
// the one the screens would write. A country reads as GET /store/countries
// lists it (api_store_country_present()).

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/country_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/state_form.php');

// One state with its country, as GET /store/states/{id} answers it.
function api_store_country_state_present($row) {

	return array(
		'id'           => (int)$row['id'],
		'country_id'   => (int)$row['country_id'],
		'country_code' => (isset($row['country_code']) && ((string)$row['country_code'] !== '')) ? (string)$row['country_code'] : null,
		'code'         => (string)$row['code'],
		'name'         => (string)$row['name']
	);

}

function api_store_country_state_schema() {

	return array(
		'id'           => 'integer',
		'country_id'   => 'integer',
		'country_code' => 'string?',
		'code'         => 'string',
		'name'         => 'string'
	);

}

// A country after a create or an update: the stored row (or the screen's
// empty form) with the fields sent laid over it, in the form helpers' shape.
// Pure.
function api_store_country_values($row, $params) {

	$values = array(
		'name'                    => ($row === null) ? '' : trim((string)$row['name']),
		'code'                    => ($row === null) ? '' : trim((string)$row['code']),
		'zip_code_required'       => ($row === null) ? 0 : (((int)$row['zip_code_required'] === 1) ? 1 : 0),
		'transit_adjustment_days' => ($row === null) ? 0 : (int)$row['transit_adjustment_days'],
		'default_selected'        => ($row === null) ? 0 : (((int)$row['default_selected'] === 1) ? 1 : 0)
	);

	foreach (array('name', 'code') as $field) {

		if (array_key_exists($field, $params)) {

			$values[$field] = trim((string)$params[$field]);

		}

	}

	if (array_key_exists('zip_code_required', $params)) {

		$values['zip_code_required'] = $params['zip_code_required'] ? 1 : 0;

	}

	if (array_key_exists('transit_adjustment_days', $params)) {

		$values['transit_adjustment_days'] = (int)$params['transit_adjustment_days'];

	}

	if (array_key_exists('default', $params)) {

		$values['default_selected'] = $params['default'] ? 1 : 0;

	}

	return $values;

}

// A state after a create or an update, as api_store_country_values(). The
// country comes as country_id or country_code; $country_id is the one the
// handler resolved (0: none sent). Pure.
function api_store_state_values($row, $params, $country_id) {

	$values = array(
		'name'       => ($row === null) ? '' : trim((string)$row['name']),
		'code'       => ($row === null) ? '' : trim((string)$row['code']),
		'country_id' => ($row === null) ? 0 : (int)$row['country_id']
	);

	foreach (array('name', 'code') as $field) {

		if (array_key_exists($field, $params)) {

			$values[$field] = trim((string)$params[$field]);

		}

	}

	if ((int)$country_id > 0) {

		$values['country_id'] = (int)$country_id;

	}

	return $values;

}

// The country a state body names: country_id, else country_code (the first
// country with it, as checkout reads a code). 0 when neither is sent; an
// unknown one is 422.
function api_store_state_country_of($params) {

	if (isset($params['country_id']) && ((int)$params['country_id'] > 0)) {

		$id = (int)api_value("SELECT id FROM countries WHERE id = '" . (int)$params['country_id'] . "' LIMIT 1");

		if ($id <= 0) {

			api_fail_validation(lang('The selected country was not found. Select a country.'), 'country_id');

		}

		return $id;

	}

	if (isset($params['country_code']) && (trim((string)$params['country_code']) !== '')) {

		$id = (int)api_value("SELECT id FROM countries WHERE code = '" . escape(trim((string)$params['country_code'])) . "' ORDER BY id ASC LIMIT 1");

		if ($id <= 0) {

			api_fail_validation(lang(array('string' => '{var:1}: there is no country with the code {var:2}.', 'vars' => array('country_code', trim((string)$params['country_code'])))), 'country_code');

		}

		return $id;

	}

	return 0;

}

// The screens' refusals: a blank name or code and bad transit days are 422;
// a code another country (or another state of the country) has is 409
// code_taken, which the screens show under the code field.
function api_store_region_refuse($errors, $code_error) {

	foreach ($errors as $field => $message) {

		api_fail_validation($message, ($field === 'country_id') ? 'country_id' : $field);

	}

	if ($code_error !== '') {

		api_fail(409, 'code_taken', $code_error, 'code');

	}

}

function api_store_country_load_or_fail($id) {

	$row = api_row("SELECT * FROM countries WHERE id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Country'));

	}

	return $row;

}

function api_store_country_answer($id, $status_code = 200) {

	$row = api_store_country_load_or_fail($id);

	$states = array();

	foreach (api_rows("SELECT id, code, name FROM states WHERE country_id = '" . (int)$id . "' ORDER BY name ASC, id ASC") as $state) {

		$states[] = array('id' => (int)$state['id'], 'code' => (string)$state['code'], 'name' => (string)$state['name']);

	}

	api_ok(api_store_country_present($row, $states), $status_code);

}

function api_store_state_load_or_fail($id) {

	$row = api_row("SELECT states.*, countries.code AS country_code FROM states
		LEFT JOIN countries ON countries.id = states.country_id
		WHERE states.id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('State'));

	}

	return $row;

}

function api_store_state_answer($id, $status_code = 200) {

	api_ok(api_store_country_state_present(api_store_state_load_or_fail($id)), $status_code);

}

// The line the activity log keeps, naming the application.
function api_store_region_log($text, $name) {

	$app = api_current_app();

	log_activity(lang(array('string' => $text, 'vars' => array($name, $app['name'], $app['api_key']))), $app['owner']['username']);

}

// GET /store/countries/{id}
function api_store_countries_get($params) {

	api_store_country_answer((int)$params['id']);

}

// The checked country of POST /store/countries or POST /store/countries/{id},
// before anything is written.
function api_store_country_prepare($params, $row) {

	$values = api_store_country_values($row, $params);

	$id = ($row === null) ? 0 : (int)$row['id'];

	api_store_region_refuse(
		pg_country_form_required_errors($values),
		pg_country_form_code_error($values['code'], ($row === null) ? null : (string)$row['code'], ($row === null) ? null : $id)
	);

	return array('id' => $id, 'values' => $values);

}

// Writes what api_store_country_prepare() checked; the country's id.
function api_store_country_commit($prepared) {

	$app = api_current_app();

	$saved = pg_store_country_save($prepared['id'], $prepared['values'], (int)$app['owner']['id']);

	if ($saved <= 0) {

		api_fail_server();

	}

	api_store_region_log(($prepared['id'] <= 0)
		? 'Country ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'Country ({var:1}) was changed through the API by the application {var:2} (key {var:3}).', $prepared['values']['name']);

	return $saved;

}

// POST /store/countries
function api_store_countries_create($params) {

	$prepared = api_store_country_prepare($params, null);

	api_dry_run_stop('created', 'country', array('name' => $prepared['values']['name'], 'code' => $prepared['values']['code']));

	api_store_country_answer(api_store_country_commit($prepared), 201);

}

// POST /store/countries/{id}
function api_store_countries_update($params) {

	$prepared = api_store_country_prepare($params, api_store_country_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'country', array('id' => $prepared['id'], 'name' => $prepared['values']['name'], 'code' => $prepared['values']['code']));

	api_store_country_answer(api_store_country_commit($prepared));

}

// DELETE /store/countries/{id} and its POST twin
function api_store_countries_delete($params) {

	$row = api_store_country_load_or_fail($params['id']);

	api_dry_run_stop('deleted', 'country', array('id' => (int)$row['id'], 'name' => (string)$row['name']));

	if (!pg_store_country_delete((int)$row['id'])) {

		api_fail_server();

	}

	api_store_region_log('Country ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).', (string)$row['name']);

	api_ok(array('id' => (int)$row['id'], 'deleted' => true));

}

// GET /store/states/{id}
function api_store_states_get($params) {

	api_store_state_answer((int)$params['id']);

}

// The checked state of POST /store/states or POST /store/states/{id}, before
// anything is written. A new state needs its country.
function api_store_state_prepare($params, $row) {

	$country_id = api_store_state_country_of($params);

	if (($row === null) && ($country_id <= 0)) {

		api_fail_validation(lang('Select a country.'), 'country_id');

	}

	$values = api_store_state_values($row, $params, $country_id);

	$id = ($row === null) ? 0 : (int)$row['id'];

	api_store_region_refuse(
		pg_state_form_required_errors($values),
		pg_state_form_code_error($values, ($row === null) ? null : array('code' => (string)$row['code'], 'country_id' => (int)$row['country_id']), ($row === null) ? null : $id)
	);

	return array('id' => $id, 'values' => $values);

}

// Writes what api_store_state_prepare() checked; the state's id.
function api_store_state_commit($prepared) {

	$app = api_current_app();

	$saved = pg_store_state_save($prepared['id'], $prepared['values'], (int)$app['owner']['id']);

	if ($saved <= 0) {

		api_fail_server();

	}

	api_store_region_log(($prepared['id'] <= 0)
		? 'State ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'State ({var:1}) was changed through the API by the application {var:2} (key {var:3}).', $prepared['values']['name']);

	return $saved;

}

// POST /store/states
function api_store_states_create($params) {

	$prepared = api_store_state_prepare($params, null);

	api_dry_run_stop('created', 'state', array('name' => $prepared['values']['name'], 'code' => $prepared['values']['code'], 'country_id' => $prepared['values']['country_id']));

	api_store_state_answer(api_store_state_commit($prepared), 201);

}

// POST /store/states/{id}
function api_store_states_update($params) {

	$prepared = api_store_state_prepare($params, api_store_state_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'state', array('id' => $prepared['id'], 'name' => $prepared['values']['name'], 'code' => $prepared['values']['code'], 'country_id' => $prepared['values']['country_id']));

	api_store_state_answer(api_store_state_commit($prepared));

}

// DELETE /store/states/{id} and its POST twin
function api_store_states_delete($params) {

	$row = api_store_state_load_or_fail($params['id']);

	api_dry_run_stop('deleted', 'state', array('id' => (int)$row['id'], 'name' => (string)$row['name']));

	if (!pg_store_state_delete((int)$row['id'])) {

		api_fail_server();

	}

	api_store_region_log('State ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).', (string)$row['name']);

	api_ok(array('id' => (int)$row['id'], 'deleted' => true));

}
