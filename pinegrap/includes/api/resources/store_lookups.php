<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The store's lookup lists over the external API: referral sources. Reads
// under orders:read, writes under store:write, with the checks of the
// screens' forms (includes/forms/) and the write path the screens call
// (includes/fn/store_lookups.php). No events.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/referral_source_form.php');

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
