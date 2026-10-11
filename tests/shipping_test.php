<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for carrier detection, pg_shipping_carrier() and
 * pg_shipping_carriers() in includes/fn/ecommerce.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every carrier entry carries the four keys pg_shipping_carrier() reads.
function test_shipping_carriers_have_every_key()
{
	foreach (pg_shipping_carriers() as $carrier => $entry) {
		foreach (array('name', 'aliases', 'url', 'patterns') as $key) {
			pg_assert_true(isset($entry[$key]), $carrier . '.' . $key);
		}
		pg_assert_contains('{code}', $entry['url'], $carrier . '.url');
	}
}

// A method code listed as an alias names the carrier outright.
function test_shipping_carrier_from_alias()
{
	pg_assert_same('aras', pg_shipping_carrier('1234567890', 'tr-aras'));
}

// A service-style method code is read a word at a time.
function test_shipping_carrier_from_method_word()
{
	pg_assert_same('mng', pg_shipping_carrier('1234567890', 'MNG Ertesi Gün'));
}

// With no method code, a 1Z number is recognised by its shape.
function test_shipping_carrier_from_ups_number_shape()
{
	pg_assert_same('ups', pg_shipping_carrier('1Z999AA10123456784', ''));
}

// Current behaviour, pinned as it is today: an 11-digit plain number with no
// method code matches the second UPS pattern, so it is read as UPS even
// though the Turkish carriers also issue plain-digit numbers. Change this
// test together with the detection, not on its own.
function test_shipping_carrier_plain_eleven_digits_reads_as_ups()
{
	pg_assert_same('ups', pg_shipping_carrier('12345678901', ''));
}
