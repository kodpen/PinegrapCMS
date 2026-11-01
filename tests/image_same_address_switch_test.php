<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the two plain-address switches of image delivery
 * (includes/image_engine.php): bit 4 (WebP at a JPEG or PNG's own address,
 * only with WebP on) and bit 8 (the CDN keeps a copy per format) read by
 * pg_image_delivery_settings() and written back by pg_image_delivery_bits(),
 * so a settings save keeps them, and the System Status line that says which
 * state a site is in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_image_same_address_read_from_the_bits()
{
	$read = function ($bits) {
		$settings = pg_image_delivery_settings(array('image_derivative_formats' => $bits));
		return $settings['same_address'];
	};

	pg_assert_same(array('on' => false, 'cdn' => false), $read(3), 'the shipped default: off');
	pg_assert_same(array('on' => true, 'cdn' => false), $read(7), 'on');
	pg_assert_same(array('on' => false, 'cdn' => false), $read(6), 'on without WebP is off');
	pg_assert_same(array('on' => true, 'cdn' => true), $read(15), 'on, CDN confirmed');
	pg_assert_same(array('on' => false, 'cdn' => true), $read(11), 'CDN confirmed, switch off');
	pg_assert_same(array('on' => false, 'cdn' => false), pg_image_delivery_settings(null)['same_address'], 'no row');
}

function test_image_delivery_bits_round_trip()
{
	foreach (array(0, 1, 2, 3, 5, 7, 9, 11, 13, 15) as $bits) {
		pg_assert_same($bits, pg_image_delivery_bits(pg_image_delivery_settings(array('image_derivative_formats' => $bits))), 'bits ' . $bits);
	}

	pg_assert_same(2, pg_image_delivery_bits(pg_image_delivery_settings(array('image_derivative_formats' => 6))), 'bit 4 without WebP is dropped');
	pg_assert_same(3, pg_image_delivery_bits(pg_image_delivery_settings(null)), 'no row: the default');
}

function test_image_same_address_status_line()
{
	$writes = array('webp' => true, 'avif' => true);
	$no_webp = array('webp' => false, 'avif' => false);
	$on = pg_image_delivery_settings(array('image_derivative_formats' => 7));
	$off = pg_image_delivery_settings(array('image_derivative_formats' => 3));
	$cdn = pg_image_delivery_settings(array('image_derivative_formats' => 15));

	pg_assert_same('off', pg_image_same_address_status($off, $writes, false), 'switched off');
	pg_assert_same('on', pg_image_same_address_status($on, $writes, false), 'on, no CDN');
	pg_assert_same('no_webp', pg_image_same_address_status($on, $no_webp, false), 'on, the server writes no WebP');
	pg_assert_same('cdn_unconfirmed', pg_image_same_address_status($on, $writes, true), 'on behind a CDN not confirmed');
	pg_assert_same('on', pg_image_same_address_status($cdn, $writes, true), 'on behind a confirmed CDN');
}
