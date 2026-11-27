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

// A site's order screens print an order kept in another currency in it, at
// rate 1 with its code after the figure; an order in base keeps the visitor's
// display currency.
function test_order_currency_display_for()
{
	$symbols = array('EUR' => '&euro;');

	pg_assert_same(null, pg_order_display_for(array('charged_currency' => ''), 'TRY', $symbols), 'an order in base');
	pg_assert_same(null, pg_order_display_for(array('charged_currency' => 'try'), 'TRY', $symbols), 'the base code');
	pg_assert_same(null, pg_order_display_for(array(), 'TRY', $symbols), 'no column');
	pg_assert_same(array('code' => 'EUR', 'symbol' => '&euro;', 'suffix' => ' EUR', 'rate' => 1.0),
		pg_order_display_for(array('charged_currency' => 'eur'), 'TRY', $symbols));
	pg_assert_same(array('code' => 'USD', 'symbol' => '', 'suffix' => ' USD', 'rate' => 1.0),
		pg_order_display_for(array('charged_currency' => 'USD'), 'TRY', $symbols), 'no symbol listed');
}

// While a display is set, the site's money functions print the amount as it
// is in the order's currency; ending it goes back to the one before.
function test_order_currency_display_scope()
{
	pg_assert_same(null, pg_order_display(), 'none set');
	pg_assert_same(null, pg_order_display_begin(0), 'no order sets none');
	pg_assert_same(null, pg_order_display());

	pg_order_display(pg_order_display_for(array('charged_currency' => 'EUR'), 'TRY', array('EUR' => '€')));

	pg_assert_same(12.5, pg_visitor_amount(12.5), 'not converted');
	pg_assert_same(array('symbol' => '€', 'suffix' => ' EUR'), pg_visitor_currency_parts(), 'its symbol and code');
	pg_assert_same(array('symbol' => '€', 'suffix' => ' EUR'), pg_visitor_currency_parts('$'), 'whatever symbol the caller passes');
	pg_assert_contains('€' . pg_format_number(12.5, 2) . ' EUR', prepare_price_for_output(1250, false, '', 'html'));
	pg_assert_same('€' . pg_format_number(12.5, 2) . ' EUR', prepare_price_for_output(1250, false, '', 'plain_text'));
	pg_assert_false(pg_order_display_converts(), 'no base total beside it');

	pg_order_display_end(null);
	pg_assert_same(null, pg_order_display(), 'ended');
}

// The orders list prints an order in base as before (an order in another
// currency reads its symbol from the currencies table, pg_order_money()).
function test_order_currency_list_amount()
{
	pg_assert_same(prepare_amount(39.95), pg_order_list_amount_html('complete', 3995, null, array('charged_currency' => '')), 'an order in base');
	pg_assert_same(prepare_amount(39.95), pg_order_list_amount_html('complete', 3995, null, array('charged_currency' => defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '')), 'the base code');
}

// The report adds orders up in base units: an order kept in another currency
// counts at its rate, and 0 when the rate is not known.
function test_order_currency_report_row_in_base()
{
	$row = array('id' => 1, 'order_number' => '1', 'order_date' => 0, 'status' => 'complete',
		'subtotal' => 1000, 'discount' => 0, 'tax' => 180, 'shipping' => 0, 'gift_card_discount' => 0,
		'surcharge' => 0, 'installment_charges' => 0, 'total' => 1180);

	pg_assert_same(1180, pg_order_report_order($row)['total'], 'an order in base');
	pg_assert_same(4130, pg_order_report_order($row + array('charged_currency' => 'ZZZ', 'charged_rate' => '3.5'))['total'], 'at its rate');
	pg_assert_same(630, pg_order_report_order($row + array('charged_currency' => 'ZZZ', 'charged_rate' => '3.5'))['tax'], 'every figure');
	pg_assert_same(0, pg_order_report_order($row + array('charged_currency' => 'ZZZ', 'charged_rate' => '0'))['total'], 'rate not known');
}
