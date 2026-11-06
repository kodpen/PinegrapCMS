<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for currency writes over the external API
 * (includes/api/resources/store_currencies.php) and the write path the
 * currency screens and the rate refresh share with it
 * (includes/fn/store_currencies.php): the route rows, partial updates laid
 * over the stored row, the screens' checks before the write, the rate and
 * symbol as stored, the codes a refresh asks for and the currency.* events.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/store_currencies.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function api_store_currencies_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function api_store_currencies_test_body($relative, $name)
{
	$source = api_store_currencies_test_source($relative);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_api_store_currencies_routes()
{
	$expected = array(
		'store.currencies.get'         => array('GET', '/store/currencies/{id}', 'orders:read'),
		'store.currencies.create'      => array('POST', '/store/currencies', 'store:write'),
		'store.currencies.rates'       => array('POST', '/store/currencies/rates', 'store:write'),
		'store.currencies.update'      => array('POST', '/store/currencies/{id}', 'store:write'),
		'store.currencies.delete'      => array('DELETE', '/store/currencies/{id}', 'store:write'),
		'store.currencies.delete_post' => array('POST', '/store/currencies/{id}/delete', 'store:write'),
	);

	// The columns: name varchar(100), code varchar(3), symbol varchar(10).
	$limits = array('name' => 100, 'code' => 3, 'symbol' => 10);
	$found = array();

	foreach (api_schema() as $route) {
		if (!isset($expected[$route['id']])) {
			continue;
		}

		$found[] = $route['id'];
		$row = $expected[$route['id']];

		pg_assert_same($row[0], $route['method'], $route['id'] . ' method');
		pg_assert_same($row[1], $route['path'], $route['id'] . ' path');
		pg_assert_same($row[2], $route['scope'], $route['id'] . ' scope');
		pg_assert_true(function_exists($route['handler']), $route['handler'] . '() exists');

		if ($route['method'] !== 'GET') {
			pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		}

		foreach ($route['params'] as $param) {
			if (isset($limits[$param['name']])) {
				pg_assert_true($param['max_length'] <= $limits[$param['name']], $route['id'] . ' ' . $param['name'] . ' fits its column');
			}

			if ($param['name'] === 'exchange_rate') {
				pg_assert_same(PG_CURRENCY_FORM_MAX_RATE, $param['max'], 'the screen\'s largest rate');
			}
		}
	}

	pg_assert_same(count($expected), count($found), 'every route is declared');
}

function test_api_store_currency_values_lay_the_sent_fields_over_the_row()
{
	$row = array('name' => 'Euro', 'code' => 'eur', 'symbol' => '&euro;', 'exchange_rate' => '0.02800', 'base' => '0');

	pg_assert_same(
		array('name' => 'Euro', 'code' => 'EUR', 'symbol' => '&euro;', 'exchange_rate' => '0.02800', 'base' => 0),
		api_store_currency_values($row, array()),
		'nothing sent keeps the row, the stored symbol as it is'
	);

	$values = api_store_currency_values($row, array('code' => ' usd ', 'symbol' => '$', 'exchange_rate' => 0.025));

	pg_assert_same('USD', $values['code'], 'trimmed and in capitals like the screen');
	pg_assert_same('$', $values['symbol']);
	pg_assert_same('0.025', $values['exchange_rate']);
	pg_assert_same('Euro', $values['name']);

	pg_assert_same(
		array('name' => '', 'code' => '', 'symbol' => '', 'exchange_rate' => '', 'base' => 0),
		api_store_currency_values(null, array()),
		'a create starts from the empty form'
	);
}

// An update leaves the exchange rate and the base switch alone unless it
// changes them: a rate refresh, or another currency made the base, landing
// between the read and the write is kept instead of overwritten with the
// figures read at the start.
function test_api_store_currency_kept_columns_unless_changed()
{
	$row = array('name' => 'Euro', 'code' => 'EUR', 'symbol' => '&euro;', 'exchange_rate' => '0.02800', 'base' => '0');

	$values = api_store_currency_values($row, array('name' => 'Euro zone'));
	$values['exchange_rate'] = api_store_currency_rate_stored($values['exchange_rate'], $values['base']);

	pg_assert_same(array('exchange_rate', 'base'), api_store_currency_kept($row, $values), 'a name change keeps the rate and the base');

	$values['exchange_rate'] = '0.02900';
	pg_assert_same(array('base'), api_store_currency_kept($row, $values), 'a new rate is written');

	$values['base'] = 1;
	$values['exchange_rate'] = api_store_currency_rate_stored($values['exchange_rate'], $values['base']);
	pg_assert_same(array(), api_store_currency_kept($row, $values), 'made the base: base and rate 1 written');

	$base_row = array('name' => 'Lira', 'code' => 'TRY', 'symbol' => '&#8378;', 'exchange_rate' => '1.00000', 'base' => '1');
	$values = api_store_currency_values($base_row, array('symbol' => 'TL'));
	$values['exchange_rate'] = api_store_currency_rate_stored($values['exchange_rate'], $values['base']);

	pg_assert_same(array('exchange_rate', 'base'), api_store_currency_kept($base_row, $values), 'the base resent keeps both');
	pg_assert_same(array(), api_store_currency_kept(null, $values), 'a create writes every column');
}

// The UPDATE the shared write builds: kept columns stay out of it. Pure.
function test_store_currency_update_sets_leave_kept_columns_out()
{
	$values = array('name' => 'Euro', 'code' => 'EUR', 'symbol' => '&euro;', 'exchange_rate' => '0.02800', 'base' => 0);

	pg_assert_same(
		array(array('name = ?', 'base = ?', 'code = ?', 'symbol = ?', 'exchange_rate = ?'), array('Euro', 0, 'EUR', '&euro;', '0.02800')),
		pg_store_currency_update_sets($values, array()),
		'nothing kept writes every column'
	);

	pg_assert_same(
		array(array('name = ?', 'code = ?', 'symbol = ?'), array('Euro', 'EUR', '&euro;')),
		pg_store_currency_update_sets($values, array('exchange_rate', 'base')),
		'a kept rate and base are not written'
	);

	$save = api_store_currencies_test_body('includes/fn/store_currencies.php', 'pg_store_currency_save');

	pg_assert_contains("!in_array('base', \$kept, true)", $save, 'the other bases are cleared only when the base is written');
	pg_assert_contains('$prepared[\'kept\']', api_store_currencies_test_body('includes/api/resources/store_currencies.php', 'api_store_currency_commit'), 'the API passes what it keeps');
}

// The API symbol is plain text both ways: escaped into the stored HTML,
// decoded back by api_currency_symbol_text().
function test_api_store_currency_symbol_round_trips_as_text()
{
	pg_assert_same('€', api_store_currency_symbol_stored(' € '));
	pg_assert_same('&lt;b&gt;', api_store_currency_symbol_stored('<b>'), 'markup is never stored as markup');
	pg_assert_same('&amp;euro;', api_store_currency_symbol_stored('&euro;'), 'an entity sent stays text');

	foreach (array('€', '₺', '<b>', '&euro;', 'R$') as $text) {
		pg_assert_same($text, api_currency_symbol_text(api_store_currency_symbol_stored($text)), $text . ' reads back');
	}
}

function test_api_store_currency_rate_as_stored_and_refused()
{
	pg_assert_same('1.00000', api_store_currency_rate_stored('0.5', 1), 'the base is 1');
	pg_assert_same('0.00006', api_store_currency_rate_stored(0.00006, 0), 'five decimals, never 6.0E-5');
	pg_assert_same('0.02800', api_store_currency_rate_stored('0.028', 0));

	pg_assert_same('', api_store_currency_rate_error('', 1), 'the base needs no rate');
	pg_assert_same('', api_store_currency_rate_error('0.00001', 0));
	pg_assert_same('', api_store_currency_rate_error('99999.99999', 0));

	foreach (array('', 'abc', '0', '-1', '0.000004') as $rate) {
		pg_assert_true(api_store_currency_rate_error($rate, 0) !== '', var_export($rate, true) . ' is refused');
	}

	pg_assert_true(api_store_currency_rate_error('100000', 0) !== '', 'above the column is refused');
}

function test_api_store_currency_errors_are_the_screen_checks()
{
	$good = array('name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'exchange_rate' => '0.028', 'base' => 0);

	pg_assert_same(array(), api_store_currency_errors($good, 0));
	pg_assert_same(array('name'), array_keys(api_store_currency_errors(array_merge($good, array('name' => '')), 0)));
	pg_assert_same(array('code'), array_keys(api_store_currency_errors(array_merge($good, array('code' => 'EURO')), 0)), 'four letters would be cut to a second EUR');
	pg_assert_same(array('symbol'), array_keys(api_store_currency_errors(array_merge($good, array('symbol' => str_repeat('&lt;', 3))), 0)), 'the stored form counts');
	pg_assert_same(array('base'), array_keys(api_store_currency_errors(array_merge($good, array('base' => 0)), 1)), 'the base cannot be switched off');
	pg_assert_same(array('exchange_rate'), array_keys(api_store_currency_errors(array_merge($good, array('exchange_rate' => '0')), 0)));
	pg_assert_same(array(), api_store_currency_errors(array_merge($good, array('exchange_rate' => '', 'base' => 1)), 1), 'the base keeps 1');
}

function test_store_currency_rates_wanted_codes()
{
	pg_assert_same(array('USD', 'EUR', 'GBP'), pg_store_currency_rates_wanted(array('try', 'usd', 'EUR'), ' gbp,eur ,, TRY', 'TRY'), 'no base, no repeat, no blank');
	pg_assert_same(array(), pg_store_currency_rates_wanted(array('TRY'), '', 'try'));
}

function test_store_currency_events_are_listed()
{
	$events = api_webhook_events();

	foreach (array('currency.created', 'currency.updated', 'currency.deleted') as $event) {
		pg_assert_true(isset($events[$event]), $event . ' is in the catalogue');
	}

	pg_assert_same(
		array('id' => 4, 'code' => 'EUR', 'exchange_rate' => 0.028, 'base' => false),
		pg_store_currency_event('EUR', '4', '0.02800', '0')
	);
}

// Each handler checks with the screen's helpers, stops for X-Dry-Run, then
// writes through the shared function.
function test_api_store_currencies_handlers_check_stop_then_write()
{
	$file = 'includes/api/resources/store_currencies.php';
	$prepare = api_store_currencies_test_body($file, 'api_store_currency_prepare');

	pg_assert_contains('api_store_currency_errors(', $prepare);
	pg_assert_contains('pg_currency_form_code_in_use(', $prepare);
	pg_assert_contains('pg_store_currency_save(', api_store_currencies_test_body($file, 'api_store_currency_commit'));

	foreach (array('api_store_currencies_create', 'api_store_currencies_update') as $handler) {
		$body = api_store_currencies_test_body($file, $handler);
		$prepare = strpos($body, '_prepare(');
		$stop = strpos($body, 'api_dry_run_stop(');
		$commit = strpos($body, '_commit(');

		pg_assert_true(($prepare !== false) && ($stop > $prepare) && ($commit > $stop), $handler . ': check, stop, write');
	}

	$delete = api_store_currencies_test_body($file, 'api_store_currencies_delete');

	pg_assert_true(strpos($delete, 'pg_currency_form_delete_error(') < strpos($delete, 'api_dry_run_stop('), 'the base is refused before the stop');
	pg_assert_true(strpos($delete, 'api_dry_run_stop(') < strpos($delete, 'pg_store_currency_delete('), 'stop, then delete');

	$rates = api_store_currencies_test_body($file, 'api_store_currencies_rates');

	pg_assert_true(strpos($rates, 'api_dry_run_stop(') < strpos($rates, 'pg_store_currency_rates_refresh('), 'stop, then refresh');

	pg_assert_false((bool) preg_match('/(INSERT INTO|UPDATE|DELETE FROM) currencies\b/', api_store_currencies_test_source($file)), 'no SQL write of its own');
}

function test_currency_screens_write_through_the_shared_functions()
{
	$calls = array(
		'add_currency.php'          => array('pg_store_currency_save('),
		'edit_currency.php'         => array('pg_store_currency_save(', 'pg_store_currency_delete('),
		'update_exchange_rates.php' => array('pg_store_currency_rates_refresh('),
	);

	foreach ($calls as $file => $needles) {
		$source = api_store_currencies_test_source($file);

		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $file);
		}

		pg_assert_false((bool) preg_match('/(INSERT INTO|UPDATE|DELETE FROM) currencies\b/', $source), $file . ' writes no currency row itself');
	}

	$save = api_store_currencies_test_body('includes/fn/store_currencies.php', 'pg_store_currency_save');

	pg_assert_contains('UPDATE currencies SET base = 0 WHERE (base = 1) AND (id != ?)', $save, 'one base currency');
	pg_assert_contains("pg_announce(\$created ? 'currency.created' : 'currency.updated'", $save);
}
