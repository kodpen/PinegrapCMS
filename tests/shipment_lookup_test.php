<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guest parcel lookup (includes/fn/shipment_lookup.php,
 * track_shipment.php): how the typed order number and e-mail are read, the
 * rule that matches them to an order, the answer that keeps only the parcel
 * fields, and the limiter's decision. The queries and the counter itself are
 * checked on a sandbox.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// An order number is read as its digits: spaces and a leading # dropped.
function test_shipment_lookup_order_number_normalised()
{
	pg_assert_same('1042', pg_shipment_lookup_order_number(' 1042 '));
	pg_assert_same('1042', pg_shipment_lookup_order_number('#1042'));
	pg_assert_same('1042', pg_shipment_lookup_order_number('10 42'));
	pg_assert_same('', pg_shipment_lookup_order_number(''));
	pg_assert_same('', pg_shipment_lookup_order_number('0'));
	pg_assert_same('', pg_shipment_lookup_order_number('1042a'));
	pg_assert_same('', pg_shipment_lookup_order_number("1' OR '1'='1"));
	pg_assert_same('', pg_shipment_lookup_order_number(str_repeat('9', 21)));
	pg_assert_same('', pg_shipment_lookup_order_number(array('1042')));
}

// An e-mail is trimmed and lower-cased; anything that is not one is ''.
function test_shipment_lookup_email_normalised()
{
	pg_assert_same('ayse@example.com', pg_shipment_lookup_email(' Ayse@Example.COM '));
	pg_assert_same('', pg_shipment_lookup_email('ayse'));
	pg_assert_same('', pg_shipment_lookup_email('@example.com'));
	pg_assert_same('', pg_shipment_lookup_email('ayse@'));
	pg_assert_same('', pg_shipment_lookup_email(str_repeat('a', 95) . '@example.com'));
	pg_assert_same('', pg_shipment_lookup_email(array('a@b.c')));
}

// The order matches when its billing address is the one typed, in any
// capitals; an order still being placed never matches.
function test_shipment_lookup_match_rule()
{
	$order = array('id' => '7', 'status' => 'complete', 'billing_email_address' => 'Ayse@Example.com ');

	pg_assert_true(pg_shipment_lookup_matches($order, 'ayse@example.com'));
	pg_assert_true(!pg_shipment_lookup_matches($order, 'other@example.com'));
	pg_assert_true(!pg_shipment_lookup_matches($order, ''));
	pg_assert_true(!pg_shipment_lookup_matches(array('status' => 'complete', 'billing_email_address' => ''), ''));
	pg_assert_true(!pg_shipment_lookup_matches(array('status' => 'incomplete') + $order, 'ayse@example.com'));
	pg_assert_true(!pg_shipment_lookup_matches(array(), 'ayse@example.com'));
	pg_assert_true(!pg_shipment_lookup_matches(false, 'ayse@example.com'));
}

// Of several orders under one number, the one whose address matches is taken.
function test_shipment_lookup_pick_order()
{
	$orders = array(
		array('id' => '9', 'status' => 'complete', 'billing_email_address' => 'b@example.com'),
		array('id' => '4', 'status' => 'complete', 'billing_email_address' => 'a@example.com'),
	);

	pg_assert_same(4, pg_shipment_lookup_pick_order($orders, 'a@example.com'));
	pg_assert_same(0, pg_shipment_lookup_pick_order($orders, 'c@example.com'));
	pg_assert_same(0, pg_shipment_lookup_pick_order(array(), 'a@example.com'));
}

// The answer holds the parcel fields only, whatever the rows carry.
function test_shipment_lookup_answer_keeps_shipping_fields_only()
{
	$ship_tos = array(
		array('id' => '3', 'shipping_method_code' => 'tr-aras', 'address_1' => 'Street 1', 'phone_number' => '555'),
		array('id' => '5', 'shipping_method_code' => ''),
	);
	$rows = array(
		3 => array(
			array('id' => '11', 'number' => '1234567890', 'carrier' => '', 'tracking_url' => '', 'status' => 'in_transit',
				'status_at' => 1700000500, 'shipped_at' => 1700000000, 'delivered_at' => 0, 'source' => 'panel', 'format_warning' => 1,
				'price' => 999, 'billing_email_address' => 'a@example.com'),
			array('id' => '12', 'number' => '  ', 'status' => 'created'),
		),
		5 => array(
			array('id' => '13', 'number' => 'AB123', 'carrier' => 'ups', 'tracking_url' => '', 'status' => 'bogus',
				'shipped_at' => 0, 'delivered_at' => 1700100000),
		),
	);

	$parcels = pg_shipment_lookup_parcels($ship_tos, $rows, '');

	pg_assert_same(2, count($parcels));

	foreach ($parcels as $parcel) {
		pg_assert_same(array('number', 'carrier_name', 'url', 'status', 'shipped_at', 'delivered_at'), array_keys($parcel));
	}

	pg_assert_same('1234567890', $parcels[0]['number']);
	pg_assert_same('Aras Kargo', $parcels[0]['carrier_name']);
	pg_assert_contains('1234567890', $parcels[0]['url']);
	pg_assert_same('in_transit', $parcels[0]['status']);
	pg_assert_same(1700000000, $parcels[0]['shipped_at']);
	pg_assert_same(0, $parcels[0]['delivered_at']);

	pg_assert_same('created', $parcels[1]['status']);
	pg_assert_same(1700100000, $parcels[1]['delivered_at']);
}

// A parcel row of an address the order does not have is never shown.
function test_shipment_lookup_answer_ignores_foreign_rows()
{
	$parcels = pg_shipment_lookup_parcels(array(array('id' => '3', 'shipping_method_code' => '')), array(8 => array(array('number' => '999'))), '');

	pg_assert_same(array(), $parcels);
}

// Ten lookups an hour pass, the eleventh is refused; a counter that could
// not be written (0) never refuses.
function test_shipment_lookup_limiter_decision()
{
	pg_assert_same(10, PG_SHIPMENT_LOOKUP_LIMIT);
	pg_assert_same(3600, PG_SHIPMENT_LOOKUP_WINDOW);
	pg_assert_true(!pg_shipment_lookup_over_limit(0));
	pg_assert_true(!pg_shipment_lookup_over_limit(1));
	pg_assert_true(!pg_shipment_lookup_over_limit(10));
	pg_assert_true(pg_shipment_lookup_over_limit(11));
}

// Only a public visitor address is counted: a private one or a listed proxy
// would put every visitor in one bucket.
function test_shipment_lookup_counted_address()
{
	pg_assert_true(pg_shipment_lookup_address_counted('8.8.8.8', false));
	pg_assert_true(!pg_shipment_lookup_address_counted('8.8.8.8', true));
	pg_assert_true(!pg_shipment_lookup_address_counted('10.0.0.4', false));
	pg_assert_true(!pg_shipment_lookup_address_counted('', false));
	pg_assert_true(pg_shipment_lookup_address_counted('2a00:1450:4001::1', false));
}
