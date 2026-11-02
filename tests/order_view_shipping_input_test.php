<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order screen's shipping input (includes/fn/order_view.php):
 * the ship and delivery date text (dotted, slashed, ISO; a four-digit year
 * and a real day only), delivery not before shipment, and the shipped
 * quantity as a whole number from 0 to the ordered quantity.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_ship_date_empty_is_no_date()
{
	pg_assert_same(array('ok' => true, 'date' => ''), pg_order_ship_date_parse('', false), 'empty');
	pg_assert_same(array('ok' => true, 'date' => ''), pg_order_ship_date_parse('   ', false), 'blank');
}

function test_order_ship_date_day_first_separators()
{
	foreach (array('03.11.2026', '3/11/2026', '03-11-2026', '3,11,2026', ' 03.11.2026 ') as $text) {
		pg_assert_same(array('ok' => true, 'date' => '2026-11-03'), pg_order_ship_date_parse($text, false), $text);
	}
}

function test_order_ship_date_month_first()
{
	pg_assert_same(array('ok' => true, 'date' => '2026-03-11'), pg_order_ship_date_parse('03/11/2026', true), 'month first');
}

function test_order_ship_date_iso()
{
	pg_assert_same(array('ok' => true, 'date' => '2026-11-05'), pg_order_ship_date_parse('2026-11-05', false), 'iso');
	pg_assert_same(array('ok' => true, 'date' => '2026-11-05'), pg_order_ship_date_parse('2026-11-5', true), 'short iso');
}

function test_order_ship_date_refuses_bad_text()
{
	foreach (array('5/11/26', '32/13/2026', '31/02/2026', 'abc', '2026-02-30', '1/1/12026', '03.11.2026x') as $text) {
		pg_assert_false(pg_order_ship_date_parse($text, false)['ok'], $text);
	}
}

function test_order_ship_dates_order()
{
	pg_assert_true(pg_order_ship_dates_in_order('2026-11-01', '2026-11-03'), 'later delivery');
	pg_assert_true(pg_order_ship_dates_in_order('2026-11-03', '2026-11-03'), 'same day');
	pg_assert_true(pg_order_ship_dates_in_order('', '2026-11-03'), 'no ship date');
	pg_assert_true(pg_order_ship_dates_in_order('2026-11-03', ''), 'no delivery date');
	pg_assert_true(pg_order_ship_dates_in_order('0000-00-00', '2026-11-03'), 'stored empty ship date');
	pg_assert_false(pg_order_ship_dates_in_order('2030-12-31', '2026-11-02'), 'delivery before shipment');
}

function test_order_shipped_quantity_blank_hides()
{
	pg_assert_same(array('ok' => true, 'show' => 0, 'quantity' => 0), pg_order_shipped_quantity('', 3), 'blank');
	pg_assert_same(array('ok' => true, 'show' => 0, 'quantity' => 0), pg_order_shipped_quantity(null, 3), 'null');
}

function test_order_shipped_quantity_whole_within_order()
{
	pg_assert_same(array('ok' => true, 'show' => 1, 'quantity' => 0), pg_order_shipped_quantity('0', 3), 'zero');
	pg_assert_same(array('ok' => true, 'show' => 1, 'quantity' => 3), pg_order_shipped_quantity(' 3 ', 3), 'all');
	pg_assert_same(array('ok' => true, 'show' => 1, 'quantity' => 2), pg_order_shipped_quantity(2, '3'), 'int');
}

function test_order_shipped_quantity_refused()
{
	foreach (array('99', '-2', '1.5', '1,5', 'abc', '4') as $value) {
		pg_assert_false(pg_order_shipped_quantity($value, 3)['ok'], (string) $value);
	}
}
