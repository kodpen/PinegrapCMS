<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A visitor's chosen currency is used only while it has an exchange rate
 * above zero: a rate of 0 (a row kept hidden from the pickers, or a rate
 * the feeds rounded away) showed every price as 0.00 and stopped donation
 * pages, which divide the typed amount by that rate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_visitor_currency_row_keeps_a_currency_with_a_rate()
{
	$row = array('id' => '3', 'code' => 'EUR', 'symbol' => '&euro;', 'exchange_rate' => '0.02650');

	pg_assert_same($row, pg_visitor_currency_row($row));
}

function test_visitor_currency_row_drops_a_zero_rate()
{
	pg_assert_same(array(), pg_visitor_currency_row(array('id' => '4', 'code' => 'GBP', 'symbol' => '&pound;', 'exchange_rate' => '0.00000')));
	pg_assert_same(array(), pg_visitor_currency_row(array('id' => '4', 'code' => 'GBP', 'symbol' => '&pound;', 'exchange_rate' => '0')));
}

function test_visitor_currency_row_drops_a_negative_or_missing_rate()
{
	pg_assert_same(array(), pg_visitor_currency_row(array('id' => '5', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => '-1')));
	pg_assert_same(array(), pg_visitor_currency_row(array('id' => '5', 'code' => 'USD', 'symbol' => '$')));
	pg_assert_same(array(), pg_visitor_currency_row(array('id' => '5', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 'abc')));
}

function test_visitor_currency_row_drops_no_row()
{
	pg_assert_same(array(), pg_visitor_currency_row(false));
	pg_assert_same(array(), pg_visitor_currency_row(''));
	pg_assert_same(array(), pg_visitor_currency_row(array('id' => '', 'code' => 'EUR', 'exchange_rate' => '1')));
}

function test_init_reads_the_visitor_currency_through_the_rate_check()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/init.php');

	pg_assert_true(strpos($source, '$visitor_currency = pg_visitor_currency_row(db_item(') !== false, 'init.php passes the visitor currency row through pg_visitor_currency_row()');
}
