<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Encryption Key card's list of sealed values a key reset
 * leaves unreadable: each area counts only non-empty sealed values, empty
 * areas drop out and the order is fixed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_encryption_key_reset_counts_every_area_in_order()
{
	$counts = pg_encryption_key_reset_counts(array(
		'connect_accounts'     => 2,
		'connect_apps'         => 3,
		'marketplace_accounts' => 1,
		'edoc_providers'       => 1,
		'mfa_keys'             => 4,
		'config'               => array(
			'parasut_credentials_enc'    => 'abc:def',
			'backup_remote_settings'     => 'abc:def',
			'oauth_google_client_secret' => 'abc',
			'product_feeds'              => json_encode(array('formats' => array(
				'google' => array('token_secret' => 'a:b'),
				'meta'   => array('token_secret' => 'c:d'),
				'tiktok' => array('token_secret' => ''),
			))),
		),
	));

	pg_assert_same(array(
		'connect'       => 5,
		'marketplace'   => 1,
		'edoc'          => 1,
		'parasut'       => 1,
		'backup'        => 1,
		'product_feeds' => 2,
		'google'        => 1,
		'mfa'           => 4,
	), $counts);
}

function test_encryption_key_reset_counts_drop_empty_areas()
{
	pg_assert_same(array(), pg_encryption_key_reset_counts(array()));

	pg_assert_same(array('mfa' => 1), pg_encryption_key_reset_counts(array(
		'connect_accounts' => 0,
		'mfa_keys'         => '1',
		'config'           => array(
			'parasut_credentials_enc'    => '',
			'backup_remote_settings'     => '  ',
			'oauth_google_client_secret' => null,
			'product_feeds'              => 'not json',
		),
	)));
}

function test_encryption_key_reset_product_feed_tokens_ignore_bad_shapes()
{
	pg_assert_same(0, pg_encryption_key_reset_feed_tokens(''));
	pg_assert_same(0, pg_encryption_key_reset_feed_tokens('{"formats":"x"}'));
	pg_assert_same(1, pg_encryption_key_reset_feed_tokens('{"formats":{"a":{"token_secret":"x:y"},"b":"bad","c":{"token_secret":["x"]}}}'));
}
