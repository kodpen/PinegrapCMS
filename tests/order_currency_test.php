<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the currency an order is charged in (includes/fn/order_currency.php):
 * the pair an order is written with, one amount and a sum in base units, the
 * symbol an amount is printed with, and the fill of the older marketplace
 * orders.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A code is three letters in upper case, '' for anything else.
function test_order_currency_code()
{
	pg_assert_same('USD', pg_order_currency_code(' usd '));
	pg_assert_same('', pg_order_currency_code(''));
	pg_assert_same('', pg_order_currency_code('US'), 'too short');
	pg_assert_same('', pg_order_currency_code("US'"), 'not a letter');
	pg_assert_same('', pg_order_currency_code(null));
}

// The base currency, or none, is stored as '' with rate 0; another currency
// takes the rate of its day, else today's, else 0.
function test_order_currency_charged_pair()
{
	pg_assert_same(array('currency' => '', 'rate' => '0.00000000'), pg_order_charged_pair('', 'TRY', 0, 0), 'no currency');
	pg_assert_same(array('currency' => '', 'rate' => '0.00000000'), pg_order_charged_pair('try', 'TRY', 40, 41), 'the base currency');
	pg_assert_same(array('currency' => 'USD', 'rate' => '32.12345679'), pg_order_charged_pair('usd', 'TRY', 32.123456789, 40), 'the day rate first');
	pg_assert_same(array('currency' => 'USD', 'rate' => '40.00000000'), pg_order_charged_pair('USD', 'TRY', 0, 40), 'today when the day has none');
	pg_assert_same(array('currency' => 'USD', 'rate' => '0.00000000'), pg_order_charged_pair('USD', 'TRY', 0, 0), 'not known');
}

// An order's amounts are in its charged currency, the base one when none.
function test_order_currency_amount_currency()
{
	pg_assert_same('TRY', pg_order_amount_currency(array(), 'TRY'), 'no column');
	pg_assert_same('TRY', pg_order_amount_currency(array('charged_currency' => ''), 'TRY'));
	pg_assert_same('EUR', pg_order_amount_currency(array('charged_currency' => 'EUR'), 'TRY'));
}

// One amount in base minor units: as it is for the base currency, times
// the rate rounded for another, 0 when the rate is not known.
function test_order_currency_base_amount()
{
	pg_assert_same(1234, pg_order_base_amount(1234, array(), 'TRY'), 'an order from before the columns');
	pg_assert_same(1234, pg_order_base_amount(1234, array('charged_currency' => '', 'charged_rate' => '0'), 'TRY'), 'a base order');
	pg_assert_same(1234, pg_order_base_amount(1234, array('charged_currency' => 'TRY', 'charged_rate' => '0'), 'TRY'), 'the base code');
	pg_assert_same(4000, pg_order_base_amount(100, array('charged_currency' => 'USD', 'charged_rate' => '40.00000000'), 'TRY'));
	pg_assert_same(3, pg_order_base_amount(1, array('charged_currency' => 'USD', 'charged_rate' => '2.5'), 'TRY'), 'half rounds away from zero');
	pg_assert_same(-3, pg_order_base_amount(-1, array('charged_currency' => 'USD', 'charged_rate' => '2.5'), 'TRY'), 'a negative amount');
	pg_assert_same(0, pg_order_base_amount(100, array('charged_currency' => 'USD', 'charged_rate' => '0'), 'TRY'), 'rate not known');
}

// The SQL counts base orders as they are, converts the others with their
// rate, and leaves the expression alone while the columns are missing.
function test_order_currency_base_sql()
{
	pg_assert_same('orders.total', pg_order_base_sql_build('orders.total', 'TRY', false), 'not ready');

	$sql = pg_order_base_sql_build('orders.total - orders.tax', 'try', true);

	pg_assert_same("(CASE WHEN orders.charged_currency IN ('', 'TRY') THEN (orders.total - orders.tax)"
		. " WHEN orders.charged_rate > 0 THEN ROUND((orders.total - orders.tax) * orders.charged_rate)"
		. " ELSE 0 END)", $sql);

	pg_assert_contains("o.charged_currency IN ('')", pg_order_base_sql_build('o.total', '', true, 'o'), 'no base code, an alias');
	pg_assert_contains("x.charged_rate", pg_order_base_sql_build('x.total', 'TRY', true, "x'; --"), 'the alias is cleaned');

	// The test run has no database, so the live helper answers as not ready.
	pg_assert_same('SUM(orders.total)', 'SUM(' . pg_order_base_sql('orders.total') . ')');
}

// The base currency prints with the base symbol; another with its own,
// else with its code after the figure.
function test_order_currency_money_parts()
{
	$symbols = array('EUR' => '&euro;', 'GBP' => ' ');

	pg_assert_same(array('symbol' => '&#8378;', 'suffix' => ''), pg_order_money_parts('', 'TRY', '&#8378;', $symbols));
	pg_assert_same(array('symbol' => '&#8378;', 'suffix' => ''), pg_order_money_parts('TRY', 'TRY', '&#8378;', $symbols));
	pg_assert_same(array('symbol' => '&euro;', 'suffix' => ''), pg_order_money_parts('EUR', 'TRY', '&#8378;', $symbols));
	pg_assert_same(array('symbol' => '', 'suffix' => ' GBP'), pg_order_money_parts('GBP', 'TRY', '&#8378;', $symbols), 'a blank symbol');
	pg_assert_same(array('symbol' => '', 'suffix' => ' USD'), pg_order_money_parts('USD', 'TRY', '&#8378;', $symbols), 'not listed');
}

// The fill takes a marketplace order in another currency that has no
// charged currency yet, nothing else.
function test_order_currency_fill_needs()
{
	$row = array('type' => 'marketplace', 'currency_code' => 'EUR', 'charged_currency' => '');

	pg_assert_true(pg_order_currency_fill_needs($row, 'TRY'));
	pg_assert_false(pg_order_currency_fill_needs(array_merge($row, array('type' => 'online')), 'TRY'), 'a web order');
	pg_assert_false(pg_order_currency_fill_needs(array_merge($row, array('currency_code' => 'try')), 'TRY'), 'the base currency');
	pg_assert_false(pg_order_currency_fill_needs(array_merge($row, array('currency_code' => '')), 'TRY'), 'no currency');
	pg_assert_false(pg_order_currency_fill_needs(array_merge($row, array('charged_currency' => 'EUR')), 'TRY'), 'already filled');
}

// The fill's place goes on past a full batch and ends after a short one;
// a broken marker starts from the beginning.
function test_order_currency_fill_cursor()
{
	pg_assert_same(array('after' => 0, 'done' => false), pg_order_currency_fill_cursor(''));
	pg_assert_same(array('after' => 0, 'done' => false), pg_order_currency_fill_cursor('{bad'));
	pg_assert_same(array('after' => 0, 'done' => true), pg_order_currency_fill_cursor('{"done":1}'));
	pg_assert_same(array('after' => 500, 'done' => false), pg_order_currency_fill_cursor('{"after":500}'));

	$cursor = array('after' => 500, 'done' => false);

	pg_assert_same(array('after' => 1500, 'done' => false), pg_order_currency_fill_next($cursor, 1500, 1000, 1000), 'a full batch');
	pg_assert_same(array('after' => 1700, 'done' => true), pg_order_currency_fill_next($cursor, 1700, 200, 1000), 'a short batch');
	pg_assert_same(array('after' => 500, 'done' => true), pg_order_currency_fill_next($cursor, 500, 0, 1000), 'nothing left');
}

// Without a database the writer leaves the columns out and the fill waits.
function test_order_currency_not_ready_without_columns()
{
	pg_assert_same(null, pg_order_charged_for('USD', 1700000000));
	pg_assert_same(array('after' => 0, 'done' => false), pg_order_currency_fill_run(1));
}
