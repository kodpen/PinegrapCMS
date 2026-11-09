<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the live rate of a shipping method linked to a carrier label
 * service (includes/fn/shipping_label_rates.php) and the parcel it shares
 * with the label (pg_shipment_label_parcel()): the lines that ship, the
 * cache key, the base-currency amounts and the quote charged.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_shipping_label_rate_lines()
{
	$items = array(
		array('quantity' => 2, 'weight' => '1.5', 'length' => '10', 'width' => '8', 'height' => '4', 'free_shipping' => '0', 'name' => 'A'),
		array('quantity' => 1, 'weight' => '3', 'length' => '20', 'width' => '2', 'height' => '2', 'free_shipping' => '1'),
		array('quantity' => 0, 'weight' => '9'),
		'not a line',
		array('quantity' => 1, 'weight' => '0.25'),
	);

	pg_assert_same(array(
		array('quantity' => 2, 'weight' => '1.5', 'length' => '10', 'width' => '8', 'height' => '4'),
		array('quantity' => 1, 'weight' => '0.25', 'length' => 0, 'width' => 0, 'height' => 0),
	), pg_shipping_label_rate_lines($items), 'free-shipping lines, no quantity and non-lines are left out');
	pg_assert_same(array(), pg_shipping_label_rate_lines(null), 'nothing');
}

function test_shipment_label_parcel()
{
	pg_assert_same(
		array('length' => 25.4, 'width' => 20.32, 'height' => 10.16, 'weight' => 1.474),
		pg_shipment_label_parcel(array(
			array('quantity' => 2, 'weight' => '1.5', 'length' => '10', 'width' => '8', 'height' => '4'),
			array('quantity' => 1, 'weight' => '0.25', 'length' => '3', 'width' => '1', 'height' => '1'),
		)),
		'weights summed by quantity, the largest size of a line, kg and cm');
	pg_assert_same(array('length' => 10.0, 'width' => 10.0, 'height' => 10.0, 'weight' => 1.0), pg_shipment_label_parcel(array()), 'an empty parcel goes as 1 kg and 10 cm');

	$shipment = pg_shipment_label_shipment(array('order_number' => '1001'), array('first_name' => 'Ada'), array(
		array('product_name' => 'A', 'quantity' => 2, 'weight' => '1.5', 'length' => '10', 'width' => '8', 'height' => '4'),
	));
	pg_assert_same(array('length' => 25.4, 'width' => 20.32, 'height' => 10.16, 'weight' => 1.361), $shipment['parcel'], 'the label carries the same parcel');
}

function test_shipping_label_rate_service_key()
{
	pg_assert_same('label:geliver:YURTICI_STANDART', pg_shipping_label_rate_service_key('geliver', 'YURTICI_STANDART'), 'a service');
	pg_assert_same('label:geliver:', pg_shipping_label_rate_service_key('geliver', ''), 'the prefix of a driver');
	pg_assert_true(strlen(pg_shipping_label_rate_service_key(str_repeat('a', 32), str_repeat('B', 60))) <= 100, 'the longest key fits shipping_rates.service');
}

function test_shipping_label_rate_in_base()
{
	$quotes = array(
		array('service' => 'YURTICI_STANDART', 'carrier' => 'yurtici', 'amount' => 9000, 'currency' => 'TRY'),
		array('service' => 'ARAS_STANDART', 'carrier' => 'aras', 'amount' => 8500, 'currency' => 'TRY'),
		array('service' => 'DHL_EXPRESS', 'carrier' => '', 'amount' => 1000, 'currency' => 'EUR'),
		array('service' => 'UPS_SAVER', 'carrier' => '', 'amount' => 1000, 'currency' => 'GBP'),
		array('service' => 'YURTICI_STANDART', 'carrier' => 'yurtici', 'amount' => 9500, 'currency' => 'TRY'),
		array('amount' => 1, 'currency' => 'TRY'),
	);

	pg_assert_same(
		array('YURTICI_STANDART' => 9000, 'ARAS_STANDART' => 8500, 'DHL_EXPRESS' => 50000),
		pg_shipping_label_rate_in_base($quotes, 'try', array('EUR' => 0.02, 'GBP' => 0)),
		'the base as it is, EUR at its rate, GBP without a rate dropped, a service once at its lower amount');
	pg_assert_same(array('DHL_EXPRESS' => 1000), pg_shipping_label_rate_in_base($quotes, 'EUR', array()), 'no rate for TRY: only the base currency\'s quote');
}

function test_shipping_label_rate_pick()
{
	$amounts = array('YURTICI_STANDART' => 9000, 'ARAS_STANDART' => 8500, 'MNG_STANDART' => 0);

	pg_assert_same(0, pg_shipping_label_rate_pick($amounts, ''), 'no service linked: the cheapest, a free one included');
	pg_assert_same(9000, pg_shipping_label_rate_pick($amounts, 'YURTICI_STANDART'), 'the linked service');
	pg_assert_same(8500, pg_shipping_label_rate_pick($amounts, ' aras_standart '), 'letter case and spaces aside');
	pg_assert_same(null, pg_shipping_label_rate_pick($amounts, 'PTT_STANDART'), 'a linked service with no quote has no price');
	pg_assert_same(null, pg_shipping_label_rate_pick(array(), ''), 'no quote, no price');
}
