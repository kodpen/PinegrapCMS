<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Currency writes (store:write): the checks of the currency screens
// (includes/forms/currency_form.php) and their write path
// (includes/fn/store_currencies.php), so a currency written here is the one
// the screens would write. A currency reads as GET /store/currencies lists it
// (api_store_currency_present()).

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/currency_form.php');

// The column holds ten characters of the symbol as the pages print it.
if (!defined('API_STORE_CURRENCY_SYMBOL_MAX')) {
	define('API_STORE_CURRENCY_SYMBOL_MAX', 10);
}

// A symbol as the currency screen stores it: HTML the pages print. The API
// takes and gives plain text (api_currency_symbol_text()), so the text is
// escaped on the way in and an entity a client sends stays text. Pure.
function api_store_currency_symbol_stored($text) {

	return htmlspecialchars(trim((string)$text), ENT_QUOTES, 'UTF-8');

}

// An exchange rate as the column keeps it: five decimals; 1 for the base.
// Pure.
function api_store_currency_rate_stored($rate, $base) {

	return ((int)$base === 1) ? '1.00000' : number_format((float)$rate, 5, '.', '');

}

// The message for a rate a non-base currency cannot be saved with, or '': it
// must be above zero once rounded to the column's five decimals, and within
// the column (the screen's limit). Pure.
function api_store_currency_rate_error($rate, $base) {

	if ((int)$base === 1) {

		return '';

	}

	if (!is_numeric($rate) || ((float)api_store_currency_rate_stored($rate, 0) <= 0)) {

		return lang('Enter an exchange rate greater than zero.');

	}

	if ((float)$rate > PG_CURRENCY_FORM_MAX_RATE) {

		return lang(array(
			'string' => 'Enter an exchange rate no greater than {var:1}.',
			'vars'   => array(h(pg_format_number(PG_CURRENCY_FORM_MAX_RATE, 5)))
		));

	}

	return '';

}

// A currency after a create or an update: the stored row (or the screen's
// empty form) with the fields sent laid over it, in the write path's shape.
// The rate stays as sent until the checks; the code is trimmed and in
// capitals, as the screen saves it. Pure.
function api_store_currency_values($row, $params) {

	$values = array(
		'name'          => ($row === null) ? '' : trim((string)$row['name']),
		'code'          => ($row === null) ? '' : pg_currency_form_code($row['code']),
		'symbol'        => ($row === null) ? '' : (string)$row['symbol'],
		'exchange_rate' => ($row === null) ? '' : (string)$row['exchange_rate'],
		'base'          => ($row === null) ? 0 : (((int)$row['base'] === 1) ? 1 : 0)
	);

	if (array_key_exists('name', $params)) {

		$values['name'] = trim((string)$params['name']);

	}

	if (array_key_exists('code', $params)) {

		$values['code'] = pg_currency_form_code($params['code']);

	}

	if (array_key_exists('symbol', $params)) {

		$values['symbol'] = api_store_currency_symbol_stored($params['symbol']);

	}

	if (array_key_exists('exchange_rate', $params)) {

		$values['exchange_rate'] = (string)$params['exchange_rate'];

	}

	if (array_key_exists('base', $params)) {

		$values['base'] = $params['base'] ? 1 : 0;

	}

	return $values;

}

// The columns an update leaves as they stand: the exchange rate and the
// base switch when the checked values do not change them. The rate refresh
// and a currency made the base elsewhere write both, so an update that only
// renames writes them back as read and undoes what landed meanwhile.
// Nothing is kept on a create. Pure.
function api_store_currency_kept($row, $values) {

	if ($row === null) {

		return array();

	}

	$kept = array();

	if (number_format((float)$row['exchange_rate'], 5, '.', '') === number_format((float)$values['exchange_rate'], 5, '.', '')) {

		$kept[] = 'exchange_rate';

	}

	if ((((int)$row['base'] === 1) ? 1 : 0) === (((int)$values['base'] === 1) ? 1 : 0)) {

		$kept[] = 'base';

	}

	return $kept;

}

// The screen's refusals for a currency, field => message: a blank name, a
// code that is not three letters, a symbol too long for the column, the
// stored base switched off, a rate the column cannot keep. Pure.
function api_store_currency_errors($values, $stored_base) {

	$errors = array();

	if ($values['name'] === '') {

		$errors['name'] = lang(array('string' => '{var:1} is required', 'vars' => array(lang(array('string' => '{var:1} Name', 'vars' => lang('Currency'))))));

	}

	if (($message = pg_currency_form_code_error($values['code'])) !== '') {

		$errors['code'] = $message;

	}

	if (mb_strlen($values['symbol'], 'UTF-8') > API_STORE_CURRENCY_SYMBOL_MAX) {

		$errors['symbol'] = lang(array('string' => '{var:1} is longer than the {var:2} characters accepted.', 'vars' => array('symbol', API_STORE_CURRENCY_SYMBOL_MAX)));

	}

	if (($message = pg_currency_form_base_error($stored_base, $values['base'])) !== '') {

		$errors['base'] = $message;

	}

	if (($message = api_store_currency_rate_error($values['exchange_rate'], $values['base'])) !== '') {

		$errors['exchange_rate'] = $message;

	}

	return $errors;

}

function api_store_currency_load_or_fail($id) {

	$row = api_row("SELECT * FROM currencies WHERE id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Currency'));

	}

	return $row;

}

function api_store_currency_answer($id, $status_code = 200) {

	api_ok(api_store_currency_present(api_store_currency_load_or_fail($id)), $status_code);

}

// The line the activity log keeps, naming the application.
function api_store_currency_log($text, $name) {

	$app = api_current_app();

	log_activity(lang(array('string' => $text, 'vars' => array($name, $app['name'], $app['api_key']))), $app['owner']['username']);

}

// GET /store/currencies/{id}
function api_store_currencies_get($params) {

	api_store_currency_answer((int)$params['id']);

}

// The checked currency of POST /store/currencies or POST
// /store/currencies/{id}, before anything is written.
function api_store_currency_prepare($params, $row) {

	$values = api_store_currency_values($row, $params);
	$id = ($row === null) ? 0 : (int)$row['id'];

	foreach (api_store_currency_errors($values, ($row === null) ? 0 : (int)$row['base']) as $field => $message) {

		api_fail_validation($message, $field);

	}

	if (pg_currency_form_code_in_use($values['code'], ($row === null) ? null : (string)$id)) {

		api_fail(409, 'code_taken', lang('The code that you entered is already in use by another currency, please enter a different code.'), 'code');

	}

	$values['exchange_rate'] = api_store_currency_rate_stored($values['exchange_rate'], $values['base']);

	return array('id' => $id, 'values' => $values, 'kept' => api_store_currency_kept($row, $values));

}

// Writes what api_store_currency_prepare() checked; the currency's id.
function api_store_currency_commit($prepared) {

	$app = api_current_app();

	$saved = pg_store_currency_save($prepared['id'], $prepared['values'], (int)$app['owner']['id'], isset($prepared['kept']) ? $prepared['kept'] : array());

	if ($saved <= 0) {

		api_fail_server();

	}

	api_store_currency_log(($prepared['id'] <= 0)
		? 'Currency ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'Currency ({var:1}) was changed through the API by the application {var:2} (key {var:3}).', $prepared['values']['name']);

	return $saved;

}

// POST /store/currencies
function api_store_currencies_create($params) {

	$prepared = api_store_currency_prepare($params, null);

	api_dry_run_stop('created', 'currency', array('name' => $prepared['values']['name'], 'code' => $prepared['values']['code']));

	api_store_currency_answer(api_store_currency_commit($prepared), 201);

}

// POST /store/currencies/{id}
function api_store_currencies_update($params) {

	$prepared = api_store_currency_prepare($params, api_store_currency_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'currency', array('id' => $prepared['id'], 'name' => $prepared['values']['name'], 'code' => $prepared['values']['code']));

	api_store_currency_answer(api_store_currency_commit($prepared));

}

// DELETE /store/currencies/{id} and its POST twin. The base currency is
// refused, as on the screen: without one every price falls back to USD.
function api_store_currencies_delete($params) {

	$row = api_store_currency_load_or_fail($params['id']);

	if (($message = pg_currency_form_delete_error((int)$row['base'])) !== '') {

		api_fail(409, 'base_currency', $message);

	}

	api_dry_run_stop('deleted', 'currency', array('id' => (int)$row['id'], 'name' => (string)$row['name']));

	if (!pg_store_currency_delete((int)$row['id'])) {

		api_fail_server();

	}

	api_store_currency_log('Currency ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).', (string)$row['name']);

	api_ok(array('id' => (int)$row['id'], 'deleted' => true));

}

// POST /store/currencies/rates: the Update Exchange Rates button. The run
// counts as the scheduled job's, as the button's does.
function api_store_currencies_rates($params) {

	api_dry_run_stop('updated', 'exchange_rates', array());

	$app = api_current_app();

	$refresh = pg_store_currency_rates_refresh((int)$app['owner']['id']);

	$log_line = pg_exchange_rates_log_line($refresh['updated'], count($refresh['wanted']));

	if ($log_line) {

		log_activity(pg_log_lang($log_line), $app['owner']['username']);

	}

	pg_cron_ran('update_exchange_rates');

	api_ok(array(
		'requested' => $refresh['wanted'],
		'updated'   => (int)$refresh['updated'],
		'failed'    => $refresh['failed'],
		'reached'   => (bool)$refresh['reached']
	));

}
