<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the list of sealed values a Reset Encryption Key leaves
 * unreadable: card order, empty areas dropped, product feed tokens counted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_key_reset_list_keeps_card_order_and_drops_empty_areas()
{
	$list = pg_key_reset_sealed_list(array(
		'mfa'         => 3,
		'parasut'     => 1,
		'connect'     => 5,
		'backup'      => 0,
		'marketplace' => '2',
	));

	pg_assert_same(array('connect' => 5, 'marketplace' => 2, 'parasut' => 1, 'mfa' => 3), $list);
}

function test_key_reset_list_is_empty_when_nothing_is_sealed()
{
	pg_assert_same(array(), pg_key_reset_sealed_list(array()));
	pg_assert_same(array(), pg_key_reset_sealed_list(array('connect' => 0, 'mfa' => -1)));
}

function test_key_reset_list_ignores_unknown_areas()
{
	pg_assert_same(array('google_signin' => 1), pg_key_reset_sealed_list(array('orders' => 9, 'google_signin' => 1)));
}

function test_key_reset_counts_cover_every_area()
{
	pg_assert_same(8, count(pg_key_reset_areas()));
}

function test_key_reset_feed_tokens_count_only_sealed_formats()
{
	$settings = pg_product_feed_settings_parse('');
	pg_assert_same(0, pg_key_reset_feed_token_count($settings));

	$formats = array_keys($settings['formats']);
	$settings['formats'][$formats[0]]['token_secret'] = 'cipher:iv';

	if (isset($formats[1])) {
		$settings['formats'][$formats[1]]['token_hash'] = 'abc';
	}

	pg_assert_same(1, pg_key_reset_feed_token_count($settings));
}

function test_key_reset_feed_tokens_tolerate_malformed_settings()
{
	pg_assert_same(0, pg_key_reset_feed_token_count(null));
	pg_assert_same(0, pg_key_reset_feed_token_count(array('formats' => 'x')));
	pg_assert_same(0, pg_key_reset_feed_token_count(array('formats' => array('a' => 'x', 'b' => array('token_secret' => 5)))));
}
