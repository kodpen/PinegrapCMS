<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for markets (includes/fn/markets.php): the rounding codes, a market
 * form's values and refusals, a country held by another market, a move in
 * the list, the countries cell, and the Markets screen's registration.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A stored rule is one of the three codes, 'none' for anything else.
function test_markets_rounding_code()
{
	pg_assert_same('whole', pg_market_rounding_code(' Whole '));
	pg_assert_same('end99', pg_market_rounding_code('end99'));
	pg_assert_same('none', pg_market_rounding_code('none'));
	pg_assert_same('none', pg_market_rounding_code('99'), 'unknown');
	pg_assert_same('none', pg_market_rounding_code(null));
	pg_assert_same(array('none', 'whole', 'end99'), array_keys(pg_market_rounding_options()));
}

// The post is trimmed and narrowed to known countries and a known currency.
function test_markets_form_values_clean()
{
	$form = pg_market_form_values(array(
		'name' => "  Europe \t zone ",
		'currency_code' => 'eur',
		'rounding' => 'end99',
		'enabled' => '1',
		'countries' => array('3', '3', '7', '99', 'x', '-1'),
	), array('TRY', 'EUR'), array(3, 7, 8));

	pg_assert_same(array(), $form['errors']);
	pg_assert_same(array('name' => 'Europe zone', 'currency_code' => 'EUR', 'rounding' => 'end99', 'enabled' => 1, 'countries' => array(3, 7)), $form['values']);
}

// A name, a store currency and one country at least are needed.
function test_markets_form_values_refused()
{
	$form = pg_market_form_values(array('name' => ' ', 'currency_code' => 'USD', 'countries' => array(99)), array('TRY', 'EUR'), array(3));

	pg_assert_same(array('name', 'currency_code', 'countries'), array_keys($form['errors']));
	pg_assert_same(0, $form['values']['enabled'], 'off when the switch is not posted');
	pg_assert_same('none', $form['values']['rounding']);

	$long = pg_market_form_values(array('name' => str_repeat('ş', PG_MARKET_NAME_MAX + 1), 'currency_code' => 'TRY', 'countries' => array(3)), array('TRY'), array(3));
	pg_assert_same(array('name'), array_keys($long['errors']), 'too long, counted in characters');

	$fits = pg_market_form_values(array('name' => str_repeat('ş', PG_MARKET_NAME_MAX), 'currency_code' => 'TRY', 'countries' => array(3)), array('TRY'), array(3));
	pg_assert_same(array(), $fits['errors']);
}

// A country is in one market: one held by another market conflicts, one the
// market itself holds does not.
function test_markets_country_conflicts()
{
	$taken = array(3 => 1, 7 => 2);

	pg_assert_same(array(7 => 2), pg_market_country_conflicts(array(3, 7, 8), $taken, 1));
	pg_assert_same(array(3 => 1, 7 => 2), pg_market_country_conflicts(array(3, 7), $taken, 0), 'a new market');
	pg_assert_same(array(), pg_market_country_conflicts(array(8), $taken, 0));
}

// A move swaps with the neighbour; the ends and an unknown id stay.
function test_markets_move_ids()
{
	pg_assert_same(array(2, 1, 3), pg_market_move_ids(array(1, 2, 3), 2, 'up'));
	pg_assert_same(array(1, 3, 2), pg_market_move_ids(array(1, 2, 3), 2, 'down'));
	pg_assert_same(array(1, 2, 3), pg_market_move_ids(array(1, 2, 3), 1, 'up'), 'top');
	pg_assert_same(array(1, 2, 3), pg_market_move_ids(array(1, 2, 3), 3, 'down'), 'bottom');
	pg_assert_same(array(1, 2, 3), pg_market_move_ids(array('1', '2', '3'), 9, 'up'), 'unknown');
}

// The list names the first three countries in the list's order, then a count.
function test_markets_country_summary()
{
	$names = array(5 => 'Austria', 2 => 'Belgium', 9 => 'France', 1 => 'Germany', 4 => 'Italy');

	pg_assert_same('Belgium, Germany', pg_market_country_summary(array(1, 2), $names));
	pg_assert_same('Austria, Belgium, France', pg_market_country_summary(array(9, 2, 5), $names));
	pg_assert_same('', pg_market_country_summary(array(), $names));
	pg_assert_true(strpos(pg_market_country_summary(array(1, 2, 4, 5, 9), $names), 'Austria, Belgium, France') === 0);
	pg_assert_true(strpos(pg_market_country_summary(array(1, 2, 4, 5, 9), $names), '2') !== false, 'the rest counted');
}

// The step creates both tables and the order column, and a reinstall knows
// the tables.
function test_markets_schema_step()
{
	$step = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_true(strpos($step, "upgrade_2026_11_0_markets();                 // 11.0.286") !== false);
	pg_assert_true(strpos($step, "install_create_table('markets'") !== false);
	pg_assert_true(strpos($step, "install_create_table('market_countries'") !== false);
	pg_assert_true(strpos($step, "UNIQUE KEY uniq_country (country_id)") !== false, 'a country in one market');
	pg_assert_true(strpos($step, "'market_id' => \"INT UNSIGNED NOT NULL DEFAULT 0\"") !== false);

	$install = file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_true(strpos($install, "'markets',") !== false);
	pg_assert_true(strpos($install, "'market_countries',") !== false);
}
